<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductMovement;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $orders = Order::where('user_id', $user->id)
            ->with(['orderProducts.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    public function show($id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::where('user_id', $user->id)
            ->where('id', $id)
            ->with(['orderProducts.product'])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($order);
    }

    public function checkout(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'delivery_address' => 'required|string',
            'contact_phone' => 'required|string',
            'payment_method' => 'required|string|in:Cash,Online',
            'payment_proof' => 'required_if:payment_method,Online|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        // Handle upload if Online Payment
        $slipImagePath = null;
        if ($request->payment_method === 'Online' && $request->hasFile('payment_proof')) {
            $slipImagePath = $request->file('payment_proof')->store('payment_slips', 'public');
        }

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            $totalDiscount = 0;
            $stockErrors = [];
            $orderItemsToCreate = [];

            // Race Condition Prevention: Lock all involved products
            // Sort by ID to prevent deadlocks
            $productIds = $cart->items->pluck('product_id')->sort()->values();
            
            // Also include potential gift products in the lock if any
            $giftProductIds = \App\Models\PromotionProduct::whereIn('product_id', $productIds)
                ->join('promotions', 'promotion_products.promotion_id', '=', 'promotions.id')
                ->where('promotions.is_active', true)
                ->whereNotNull('promotions.gift_product_id')
                ->pluck('promotions.gift_product_id');
            
            $allNeededProductIds = $productIds->concat($giftProductIds)->unique()->sort();
            
            $products = Product::with(['promotions' => function($q) {
                $q->active();
            }])->whereIn('id', $allNeededProductIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($cart->items as $item) {
                $product = $products->get($item->product_id);
                if (!$product) continue;

                // 1. Check stock for main item
                $currentStock = ProductMovement::where('product_id', $product->id)->sum('instock_quantity');
                if ($currentStock < $item->quantity) {
                    $stockErrors[] = "{$product->name} — only {$currentStock} in stock, but {$item->quantity} requested.";
                    continue;
                }

                // 2. Promotion Logic (Mirror POS)
                $standardPrice = $product->getEffectivePrice();
                $finalPrice = $standardPrice;
                $promo = $product->promotions->first(); // Standard: one active promo per product
                
                if ($promo) {
                    $val = (float)$promo->discount_value;
                    if ($promo->type === 'percentage') {
                        $finalPrice = $standardPrice - ($standardPrice * ($val / 100));
                    } elseif ($promo->type === 'fixed_amount') {
                        $finalPrice = max(0, $standardPrice - $val);
                    }
                }

                // 3. Get latest purchase price for profitability
                $latestBatch = ProductMovement::where('product_id', $product->id)
                    ->whereIn('movement_type', ['current', 'stored'])
                    ->where('instock_quantity', '>', 0)
                    ->orderBy('movement_date', 'desc')
                    ->first();
                $costPrice = $latestBatch ? $latestBatch->purchase_price : 0;

                $totalAmount += ($finalPrice * $item->quantity);
                $totalDiscount += ($standardPrice - $finalPrice) * $item->quantity;

                $orderItemsToCreate[] = [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $finalPrice,
                    'original_price' => $standardPrice,
                    'purchase_price' => $costPrice,
                    'is_gift' => false,
                ];

                // 4. Handle Gifts (BOGO / Gift)
                if ($promo) {
                    if ($promo->type === 'buy_one_get_one') {
                        $giftQty = $item->quantity;
                        // Check gift stock
                        if ($currentStock < ($item->quantity + $giftQty)) {
                            $stockErrors[] = "Insufficient stock for free gift ({$product->name})";
                        } else {
                            $orderItemsToCreate[] = [
                                'product_id' => $item->product_id,
                                'quantity' => $giftQty,
                                'price' => 0,
                                'original_price' => $standardPrice,
                                'purchase_price' => $costPrice,
                                'is_gift' => true,
                            ];
                        }
                    } elseif ($promo->type === 'buy_one_get_gift' && $promo->gift_product_id) {
                        $giftProduct = $products->get($promo->gift_product_id);
                        if ($giftProduct) {
                            $giftQty = $item->quantity * ($promo->gift_qty ?: 1);
                            $giftStock = ProductMovement::where('product_id', $giftProduct->id)->sum('instock_quantity');
                            
                            if ($giftStock < $giftQty) {
                                $stockErrors[] = "Insufficient stock for gift product ({$giftProduct->name})";
                            } else {
                                $orderItemsToCreate[] = [
                                    'product_id' => $giftProduct->id,
                                    'quantity' => $giftQty,
                                    'price' => 0,
                                    'original_price' => $giftProduct->price,
                                    'purchase_price' => 0, // cost tracking for gifts is optional
                                    'is_gift' => true,
                                ];
                            }
                        }
                    }
                }
            }

            if (!empty($stockErrors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Inventory/Promotion validation failed:',
                    'stock_errors' => $stockErrors
                ], 422);
            }

            // 5. Apply Order-Level Promotions (Global discounts)
            $orderPromo = \App\Models\Promotion::active()
                ->where('promotion_scope', 'order')
                ->where('min_order_value', '<=', $totalAmount)
                ->first();
            
            if ($orderPromo) {
                $orderDiscount = 0;
                $val = (float)$orderPromo->discount_value;
                if ($orderPromo->type === 'percentage') {
                    $orderDiscount = $totalAmount * ($val / 100);
                } elseif ($orderPromo->type === 'fixed_amount') {
                    $orderDiscount = min($totalAmount, $val);
                }

                // Apply max discount cap if defined
                if ($orderPromo->max_discount_amount > 0) {
                    $orderDiscount = min($orderDiscount, $orderPromo->max_discount_amount);
                }

                $totalAmount -= $orderDiscount;
                $totalDiscount += $orderDiscount;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'order_type' => 'online',
                'total_amount' => $totalAmount,
                'discount_amount' => $totalDiscount,
                'status' => 'pending',
                'delivery_address' => $request->delivery_address,
                'contact_phone' => $request->contact_phone,
                'payment_method' => $request->payment_method,
                'slip_image' => $slipImagePath,
            ]);

            foreach ($orderItemsToCreate as $itemData) {
                OrderProduct::create(array_merge($itemData, ['order_id' => $order->id]));

                // Deduct inventory
                ProductMovement::create([
                    'product_id' => $itemData['product_id'],
                    'movement_type' => 'sold-out',
                    'instock_quantity' => -$itemData['quantity'],
                    'expired_date' => now()->toDateString(),
                    'sale_price' => $itemData['price'],
                    'movement_date' => now(),
                    'created_by' => $user->id,
                ]);

                // Low Stock Email Alert
                $product = $products->get($itemData['product_id']);
                if ($product && $product->minimum_quantity > 0) {
                    $newStock = ProductMovement::where('product_id', $product->id)->sum('instock_quantity');
                    if ($newStock <= $product->minimum_quantity) {
                        try {
                            $adminEmail = env('ADMIN_EMAIL', 'support@vitalcare.com');
                            \Illuminate\Support\Facades\Mail::to($adminEmail)
                                ->send(new \App\Mail\LowStockAlert($product, $newStock));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Failed to send low stock email: " . $e->getMessage());
                        }
                    }
                }
            }

            // Clear cart
            $cart->items()->delete();

            DB::commit();

            // Send Order Confirmation Email
            try {
                \Illuminate\Support\Facades\Mail::to($user->email)
                    ->send(new \App\Mail\OrderConfirmed($order));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send order confirmation email: " . $e->getMessage());
            }

            return response()->json(['message' => 'Order placed successfully', 'order' => $order->load('orderProducts.product')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }
}
