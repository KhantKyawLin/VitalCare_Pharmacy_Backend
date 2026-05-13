<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminPOSController extends Controller
{
    /**
     * Search products for POS via barcode (ID) or name.
     * Also returns current stock for each product.
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        
        $promotionFilter = function($q) {
            $q->where('is_active', true)
              ->where('start_date', '<=', now())
              ->where('end_date', '>=', now())
              ->with('giftProduct');
        };

        if (!$query) {
            $products = Product::with(['category', 'promotions' => $promotionFilter, 'movements'])->limit(20)->get();
            return response()->json($this->attachStock($products));
        }

        // 1. Try exact ID match first (barcode = product ID)
        $exactProduct = Product::with(['category', 'promotions' => $promotionFilter, 'movements'])->find($query);
        
        if ($exactProduct) {
            return response()->json($this->attachStock(collect([$exactProduct])));
        }

        // 2. Fallback to name search
        $products = Product::with(['category', 'promotions' => $promotionFilter, 'movements'])
            ->where('name', 'like', '%' . $query . '%')
            ->limit(10)
            ->get();

        return response()->json($this->attachStock($products));
    }

    /**
     * Calculate and attach current stock to each product.
     */
    private function attachStock($products)
    {
        return $products->map(function ($product) {
            $movements = $product->movements ?? collect();
            $stock = $movements->reduce(function ($sum, $m) {
                return $m->movement_type !== 'sold-out' 
                    ? $sum + (int)$m->instock_quantity 
                    : $sum + (int)$m->instock_quantity; // instock_quantity is negative for sold-out
            }, 0);
            
            // --- PRICE LOGIC ---
            // If price is not set manually, use 10% markup of latest purchase price
            if (!$product->price || $product->price <= 0) {
                $latestMovement = $movements->whereIn('movement_type', ['current', 'stored'])
                    ->where('purchase_price', '>', 0)
                    ->sortByDesc('id')
                    ->first();
                
                if ($latestMovement) {
                    $product->price = $latestMovement->purchase_price * 1.10;
                }
            }

            $product->current_stock = $stock;
            unset($product->movements); // Don't send full movements to frontend
            return $product;
        });
    }

    /**
     * Process the POS checkout transaction.
     */
    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'total_order_amount' => 'required|numeric|min:0',
            'discount_amount' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'received_amount' => 'nullable|numeric|min:0',
            'change_return' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $stockErrors = [];
            
            // --- Race Condition Prevention: Pessimistic Locking ---
            // 1. Get unique product IDs and sort them to prevent deadlocks
            $productIds = collect($request->items)->pluck('product_id')->unique()->sort()->values();
            
            // 2. Lock products for update
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            // 3. Re-verify stock inside the transaction lock
            foreach ($request->items as $item) {
                $product = $products->get($item['product_id']);
                if (!$product) {
                    $stockErrors[] = "Product ID {$item['product_id']} not found.";
                    continue;
                }

                $currentStock = ProductMovement::where('product_id', $product->id)->sum('instock_quantity');

                if ($currentStock < $item['quantity']) {
                    $stockErrors[] = "{$product->name} — only {$currentStock} in stock, but {$item['quantity']} requested.";
                }
            }

            if (!empty($stockErrors)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock for the following items:',
                    'stock_errors' => $stockErrors
                ], 422);
            }
            // Generate a unique receipt number
            $receiptNumber = 'VCP-' . strtoupper(Str::random(8)) . '-' . time();

            // Create the order
            $order = Order::create([
                'user_id' => null, // Walk-in customer
                'order_type' => 'walk-in',
                'total_amount' => $request->total_order_amount,
                'discount_amount' => $request->discount_amount,
                'tax_amount' => $request->tax_amount,
                'received_amount' => $request->received_amount,
                'change_return' => $request->change_return,
                'status' => 'completed',
                'deliver_status' => 'delivered',
                'payment_status' => 'paid',
                'receipt_number' => $receiptNumber,
                'cashier_id' => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                
                // Fetch the latest purchase price for profitability tracking
                $latestBatch = ProductMovement::where('product_id', $item['product_id'])
                    ->whereIn('movement_type', ['current', 'stored'])
                    ->where('instock_quantity', '>', 0)
                    ->orderBy('movement_date', 'desc')
                    ->first();
                
                $costPrice = $latestBatch ? $latestBatch->purchase_price : 0;
                $standardPrice = $product ? $product->price : $item['price'];

                // 1. Create OrderProduct record with original price and cost tracking
                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'original_price' => $standardPrice,
                    'purchase_price' => $costPrice,
                    'is_gift' => isset($item['isGift']) && $item['isGift'],
                ]);

                // 2. Deduct inventory via ProductMovement
                ProductMovement::create([
                    'product_id' => $item['product_id'],
                    'supply_product_id' => null,
                    'movement_type' => 'sold-out',
                    'instock_quantity' => -$item['quantity'],
                    'expired_date' => now()->toDateString(),
                    'sale_price' => $item['price'],
                    'movement_date' => now(),
                    'created_by' => auth()->id(),
                ]);

                // 3. Low Stock Email Alert
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

            DB::commit();

            // Real-time WebSocket Alert for New Order
            event(new \App\Events\NewOrderAlert($order->load('orderProducts.product')));

            return response()->json([
                'status' => 'success',
                'message' => 'Checkout completed successfully',
                'order' => $order->load('orderProducts.product')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Checkout failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
