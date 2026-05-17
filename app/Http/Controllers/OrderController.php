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

        $requiresPrescription = $cart->items->contains(function ($item) {
            return $item->product && $item->product->requires_prescription;
        });

        if ($requiresPrescription && !$request->hasFile('prescription_image')) {
            return response()->json(['message' => 'A prescription image is required for one or more items in your cart.'], 400);
        }

        $prescriptionImagePath = null;
        if ($requiresPrescription && $request->hasFile('prescription_image')) {
            $prescriptionImagePath = $request->file('prescription_image')->store('prescriptions', 'public');
        }


        $slipImagePath = null;
        if ($request->payment_method === 'Online' && $request->hasFile('payment_proof')) {
            $file = $request->file('payment_proof');
            $imagePath = $file->path();
            $mime = $file->getMimeType();
            $image = null;
            
            if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
                $image = @imagecreatefromjpeg($imagePath);
            } elseif ($mime == 'image/png') {
                $image = @imagecreatefrompng($imagePath);
            }
            
            if ($image) {
                $filename = 'payment_slips/' . uniqid() . '_' . time() . '.jpg';
                $fullPath = storage_path('app/public/' . $filename);
                
                if (!file_exists(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0755, true);
                }
                
                // Compress to 70% quality
                imagejpeg($image, $fullPath, 70);
                imagedestroy($image);
                $slipImagePath = $filename;
            } else {
                $slipImagePath = $file->store('payment_slips', 'public');
            }
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

                // 1. Check stock and FEFO Batch Selection
                $batches = ProductMovement::where('product_id', $product->id)
                    ->whereIn('movement_type', ['current', 'stored'])
                    ->where('expired_date', '>=', now())
                    ->orderBy('expired_date', 'asc')
                    ->get();
                
                $qtyNeeded = $item->quantity;
                $fefoBatchesToConsume = [];
                $totalAvailable = 0;

                foreach($batches as $batch) {
                    $consumed = \DB::table('order_product_batches')->where('product_movement_id', $batch->id)->sum('quantity');
                    $available = $batch->instock_quantity - $consumed;
                    if ($available > 0) {
                        $totalAvailable += $available;
                        $take = min($available, $qtyNeeded);
                        if ($take > 0) {
                            $fefoBatchesToConsume[] = [
                                'product_movement_id' => $batch->id,
                                'quantity' => $take
                            ];
                            $qtyNeeded -= $take;
                        }
                    }
                    if ($qtyNeeded <= 0) break;
                }

                if ($qtyNeeded > 0) {
                    $stockErrors[] = "{$product->name} — only {$totalAvailable} unexpired stock available, but {$item->quantity} requested.";
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
                    'fefo_batches' => $fefoBatchesToConsume,
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
                                'fefo_batches' => [], // Skipping FEFO for simple BOGO gift clone for now
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
                                    'fefo_batches' => [], // Gifts might not strict FEFO for simplicity or can be added later
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
                'prescription_image' => $prescriptionImagePath,
                'prescription_status' => $requiresPrescription ? 'pending' : 'none',
            ]);

            foreach ($orderItemsToCreate as $itemData) {
                $fefoBatches = $itemData['fefo_batches'] ?? [];
                unset($itemData['fefo_batches']);

                $op = OrderProduct::create(array_merge($itemData, ['order_id' => $order->id]));

                foreach($fefoBatches as $b) {
                    \DB::table('order_product_batches')->insert([
                        'order_product_id' => $op->id,
                        'product_movement_id' => $b['product_movement_id'],
                        'quantity' => $b['quantity'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

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
                            
                            // Real-time WebSocket Alert
                            event(new \App\Events\LowStockAlert($product));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Failed to send low stock email: " . $e->getMessage());
                        }
                    }
                }
            }

            // Clear cart
            $cart->items()->delete();

            DB::commit();

            // Real-time WebSocket Alert for New Online Order
            event(new \App\Events\NewOrderAlert($order->load('orderProducts.product', 'user')));

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
    public function reorder($id)
    {
        $user = auth('api')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $order = Order::where('user_id', $user->id)
            ->where('id', $id)
            ->with('orderProducts')
            ->first();
        
        if (!$order) return response()->json(['message' => 'Order not found'], 404);
        
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        
        $addedCount = 0;
        foreach($order->orderProducts as $op) {
            // Only reorder actual products, not gifts from previous promotions
            if (!$op->is_gift) {
                $cartItem = \App\Models\CartItem::where('cart_id', $cart->id)
                    ->where('product_id', $op->product_id)
                    ->first();
                
                if ($cartItem) {
                    $cartItem->increment('quantity', $op->quantity);
                } else {
                    \App\Models\CartItem::create([
                        'cart_id' => $cart->id,
                        'product_id' => $op->product_id,
                        'quantity' => $op->quantity
                    ]);
                }
                $addedCount++;
            }
        }
        
        return response()->json([
            'message' => "Successfully re-added {$addedCount} items to your cart.",
            'cart_count' => \App\Models\CartItem::where('cart_id', $cart->id)->sum('quantity')
        ]);
    }
}
