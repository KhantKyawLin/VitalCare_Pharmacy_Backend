<?php

namespace App\Actions;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class CheckoutAction
{
    protected $allocateFefoStock;
    protected $applyPromotions;

    public function __construct(
        AllocateFefoStockAction $allocateFefoStock,
        ApplyPromotionsAction $applyPromotions
    ) {
        $this->allocateFefoStock = $allocateFefoStock;
        $this->applyPromotions = $applyPromotions;
    }

    /**
     * Coordinate the checkout transaction safely with product locks and action helpers.
     *
     * @param User $user
     * @param array $data
     * @param string|null $prescriptionPath
     * @param string|null $slipPath
     * @return Order
     * @throws Exception
     */
    public function execute(User $user, array $data, ?string $prescriptionPath, ?string $slipPath): Order
    {
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw new Exception('Cart is empty', 400);
        }

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            $totalDiscount = 0;
            $stockErrors = [];
            $orderItemsToCreate = [];

            // 1. Race Condition Prevention: Lock all involved product IDs
            $productIds = $cart->items->pluck('product_id')->sort()->values();
            
            $giftProductIds = \App\Models\PromotionProduct::whereIn('product_id', $productIds)
                ->join('promotions', 'promotion_products.promotion_id', '=', 'promotions.id')
                ->where('promotions.is_active', true)
                ->whereNotNull('promotions.gift_product_id')
                ->pluck('promotions.gift_product_id');
            
            $allNeededProductIds = $productIds->concat($giftProductIds)->unique()->sort();
            
            $products = Product::with(['promotions' => function($q) {
                $q->active();
            }])->whereIn('id', $allNeededProductIds)->lockForUpdate()->get()->keyBy('id');

            // 2. Iterate Cart Items
            foreach ($cart->items as $item) {
                $product = $products->get($item->product_id);
                if (!$product) continue;

                // Validate and allocate FEFO unexpired stock
                $allocation = $this->allocateFefoStock->execute($product, $item->quantity);

                if ($allocation['unmet_quantity'] > 0) {
                    $stockErrors[] = "{$product->name} — only {$allocation['available']} unexpired stock available, but {$item->quantity} requested.";
                    continue;
                }

                // Process item-level promotions
                $calculatedItems = $this->applyPromotions->execute($product, $item->quantity, $products);

                // Double check gift stocks to prevent backend errors (fixed original bug!)
                foreach ($calculatedItems as $calculatedItem) {
                    if ($calculatedItem['is_gift']) {
                        $giftAlloc = $this->allocateFefoStock->execute(
                            $products->get($calculatedItem['product_id']),
                            $calculatedItem['quantity']
                        );
                        if ($giftAlloc['unmet_quantity'] > 0) {
                            $stockErrors[] = "Insufficient stock for free gift promotion attached to {$product->name}.";
                            continue 2;
                        }
                        $calculatedItem['fefo_batches'] = $giftAlloc['batches'];
                    } else {
                        $calculatedItem['fefo_batches'] = $allocation['batches'];
                    }

                    $totalAmount += ($calculatedItem['price'] * $calculatedItem['quantity']);
                    $totalDiscount += ($calculatedItem['original_price'] - $calculatedItem['price']) * $calculatedItem['quantity'];
                    
                    $orderItemsToCreate[] = $calculatedItem;
                }
            }

            if (!empty($stockErrors)) {
                DB::rollBack();
                $errorResponse = new Exception('Inventory or Promotion validation failed');
                $errorResponse->stock_errors = $stockErrors;
                throw $errorResponse;
            }

            // 3. Global Order-Level Promotions
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

                if ($orderPromo->max_discount_amount > 0) {
                    $orderDiscount = min($orderDiscount, $orderPromo->max_discount_amount);
                }

                $totalAmount -= $orderDiscount;
                $totalDiscount += $orderDiscount;
            }

            $requiresPrescription = $cart->items->contains(function ($item) {
                return $item->product && $item->product->requires_prescription;
            });

            // 4. Create main Order record
            $order = Order::create([
                'user_id' => $user->id,
                'order_type' => 'online',
                'total_amount' => $totalAmount,
                'discount_amount' => $totalDiscount,
                'status' => 'pending',
                'delivery_address' => $data['delivery_address'],
                'contact_phone' => $data['contact_phone'],
                'payment_method' => $data['payment_method'],
                'slip_image' => $slipPath,
                'prescription_image' => $prescriptionPath,
                'prescription_status' => $requiresPrescription ? 'pending' : 'none',
            ]);

            // 5. Create Order Products, Batch Consumptions, and Deduct Stock
            foreach ($orderItemsToCreate as $itemData) {
                $fefoBatches = $itemData['fefo_batches'] ?? [];
                unset($itemData['fefo_batches']);

                $op = OrderProduct::create(array_merge($itemData, ['order_id' => $order->id]));

                foreach ($fefoBatches as $b) {
                    DB::table('order_product_batches')->insert([
                        'order_product_id' => $op->id,
                        'product_movement_id' => $b['product_movement_id'],
                        'quantity' => $b['quantity'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Create negative movement representing stock consumption
                ProductMovement::create([
                    'product_id' => $itemData['product_id'],
                    'movement_type' => 'sold-out',
                    'instock_quantity' => -$itemData['quantity'],
                    'expired_date' => now()->toDateString(),
                    'sale_price' => $itemData['price'],
                    'movement_date' => now(),
                    'created_by' => $user->id,
                ]);

                // Check Minimum Quantities for Low Stock Alert
                $product = $products->get($itemData['product_id']);
                if ($product && $product->minimum_quantity > 0) {
                    $newStock = ProductMovement::where('product_id', $product->id)->sum('instock_quantity');
                    if ($newStock <= $product->minimum_quantity) {
                        try {
                            $adminEmail = env('ADMIN_EMAIL', 'support@vitalcare.com');
                            Mail::to($adminEmail)->send(new \App\Mail\LowStockAlert($product, $newStock));
                            event(new \App\Events\LowStockAlert($product));
                        } catch (\Exception $e) {
                            Log::error("Failed to send low stock alert: " . $e->getMessage());
                        }
                    }
                }
            }

            // Clear active Cart
            $cart->items()->delete();

            DB::commit();

            // 6. Broadcast Real-Time Events & Send Mails Post-Commit
            try {
                event(new \App\Events\OrderStatusUpdated($order->load('orderProducts.product', 'user')));
            } catch (\Exception $e) {
                Log::error("Failed to dispatch OrderStatusUpdated event: " . $e->getMessage());
            }

            try {
                Mail::to($user->email)->send(new \App\Mail\OrderConfirmed($order));
            } catch (\Exception $e) {
                Log::error("Failed to send checkout confirmation email: " . $e->getMessage());
            }

            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
