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
        
        if (!$query) {
            $products = Product::with(['category', 'promotions', 'movements'])->limit(20)->get();
            return response()->json($this->attachStock($products));
        }

        // 1. Try exact ID match first (barcode = product ID)
        $exactProduct = Product::with(['category', 'promotions', 'movements'])->find($query);
        
        if ($exactProduct) {
            return response()->json($this->attachStock(collect([$exactProduct])));
        }

        // 2. Fallback to name search
        $products = Product::with(['category', 'promotions', 'movements'])
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

        // --- Stock Validation ---
        $stockErrors = [];
        foreach ($request->items as $item) {
            $product = Product::with('movements')->find($item['product_id']);
            if (!$product) {
                $stockErrors[] = "Product ID {$item['product_id']} not found.";
                continue;
            }

            $currentStock = $product->movements->reduce(function ($sum, $m) {
                return $sum + (int)$m->instock_quantity;
            }, 0);

            if ($currentStock < $item['quantity']) {
                $stockErrors[] = "{$product->name} — only {$currentStock} in stock, but {$item['quantity']} requested.";
            }
        }

        if (!empty($stockErrors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient stock for the following items:',
                'stock_errors' => $stockErrors
            ], 422);
        }

        DB::beginTransaction();
        try {
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
                'receipt_number' => $receiptNumber,
                'cashier_id' => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                // 1. Create OrderProduct record
                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
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
            }

            DB::commit();

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
