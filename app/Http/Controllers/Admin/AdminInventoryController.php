<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\ProductMovement;
use App\Models\PurchaseProduct;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminInventoryController extends Controller
{
    /**
     * Purchase history.
     */
    public function purchaseIndex(Request $request)
    {
        $query = Purchase::with(['supplier', 'user', 'purchaseProducts.productMovement.product']);
        
        // Stats
        $totalPurchases = Purchase::count();
        $totalValue = Purchase::sum('total_purchase_amount');
        $thisMonthValue = Purchase::whereMonth('purchase_date', now()->month)
            ->whereYear('purchase_date', now()->year)
            ->sum('total_purchase_amount');
        
        $topSupplier = Purchase::select('supplier_id', DB::raw('SUM(total_purchase_amount) as total'))
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->with('supplier')
            ->first();

        return response()->json([
            'purchases' => $query->latest('purchase_date')->paginate($request->get('per_page', 15)),
            'stats' => [
                'total_purchases' => $totalPurchases,
                'total_value' => $totalValue,
                'this_month_value' => $thisMonthValue,
                'top_supplier' => $topSupplier ? [
                    'name' => $topSupplier->supplier->name,
                    'total' => $topSupplier->total
                ] : null
            ]
        ]);
    }

    /**
     * Create a purchase (stock-in).
     */
    public function purchaseStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.sale_price' => 'required|numeric|min:0',
            'items.*.expired_date' => 'required|date|after:today',
            'items.*.manufactured_date' => 'nullable|date',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        DB::beginTransaction();
        try {
            $totalAmount = 0;

            $purchase = Purchase::create([
                'supplier_id' => $request->supplier_id,
                'user_id' => auth('api')->id(),
                'purchase_date' => $request->purchase_date,
                'total_purchase_amount' => 0,
            ]);

            foreach ($request->items as $item) {
                // Create product movement
                $movement = ProductMovement::create([
                    'product_id' => $item['product_id'],
                    'instock_quantity' => $item['quantity'],
                    'manufactured_date' => $item['manufactured_date'] ?? null,
                    'expired_date' => $item['expired_date'],
                    'movement_type' => 'current',
                    'purchase_price' => $item['purchase_price'],
                    'sale_price' => $item['sale_price'],
                    'created_by' => auth('api')->id(),
                ]);

                PurchaseProduct::create([
                    'purchase_id' => $purchase->id,
                    'product_movement_id' => $movement->id,
                    'purchase_quantity' => $item['quantity'],
                ]);

                $totalAmount += $item['purchase_price'] * $item['quantity'];
            }

            $purchase->update(['total_purchase_amount' => $totalAmount]);

            DB::commit();

            ActivityLog::log('created', 'Purchase', $purchase->id, "Purchase #{$purchase->id} created with " . count($request->items) . " items");

            return response()->json([
                'message' => 'Purchase created successfully',
                'purchase' => $purchase->load('purchaseProducts.productMovement.product')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Purchase details.
     */
    public function purchaseShow($id)
    {
        return response()->json(
            Purchase::with(['supplier', 'user', 'purchaseProducts.productMovement.product'])->findOrFail($id)
        );
    }

    /**
     * Low stock alerts.
     */
    public function lowStock()
    {
        $products = Product::with(['unit', 'category', 'movements' => function($q) {
            $q->whereIn('movement_type', ['current', 'stored', 'sold-out'])
              ->orderBy('movement_date', 'desc')
              ->with('purchaseProduct.purchase.supplier');
        }])
            ->whereRaw('(SELECT COALESCE(SUM(instock_quantity), 0) FROM product_movements WHERE product_id = products.id AND movement_type IN ("current", "stored") AND (expired_date IS NULL OR expired_date > CURDATE())) <= minimum_quantity')
            ->get();

        $alerts = $products->map(function ($product) {
            $currentStock = $product->movements->whereIn('movement_type', ['current', 'stored'])->sum('instock_quantity');
            $latestMovement = $product->movements->first();
            
            $supplierName = '-';
            $supplierId = null;
            $lastStockedDate = '-';

            if ($latestMovement) {
                // Handle different date formats or nulls
                try {
                    $lastStockedDate = $latestMovement->movement_date ? $latestMovement->movement_date->format('M d, Y') : '-';
                } catch (\Exception $e) {
                    $lastStockedDate = '-';
                }

                if ($latestMovement->purchaseProduct && $latestMovement->purchaseProduct->purchase && $latestMovement->purchaseProduct->purchase->supplier) {
                    $supplierName = $latestMovement->purchaseProduct->purchase->supplier->name;
                    $supplierId = $latestMovement->purchaseProduct->purchase->supplier->id;
                }
            }

            // Severity calculation
            $severity = 'notice'; // default within minimum quantity
            if ($product->minimum_quantity > 0) {
                $ratio = $currentStock / $product->minimum_quantity;
                if ($ratio <= 0.3) {
                    $severity = 'critical';
                } elseif ($ratio <= 0.6) {
                    $severity = 'warning';
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category ? $product->category->name : '-',
                'current_stock' => $currentStock,
                'reorder_level' => $product->minimum_quantity,
                'supplier_name' => $supplierName,
                'supplier_id' => $supplierId,
                'last_stocked_date' => $lastStockedDate,
                'severity' => $severity,
                // Pass product details needed for purchase form
                'price' => $product->price,
            ];
        });

        // Calculate Stats
        $stats = [
            'critical' => $alerts->where('severity', 'critical')->count(),
            'warning' => $alerts->where('severity', 'warning')->count(),
            'notice' => $alerts->where('severity', 'notice')->count(),
            'total' => $alerts->count(),
        ];

        return response()->json([
            'alerts' => $alerts->values(),
            'stats' => $stats
        ]);
    }

    /**
     * Items expiring soon (within 30 days).
     */
    public function expiringSoon()
    {
        $movements = ProductMovement::with(['product.category', 'product.unit'])
            ->where('instock_quantity', '>', 0)
            ->whereIn('movement_type', ['current', 'stored'])
            ->whereBetween('expired_date', [now(), now()->addDays(30)])
            ->get();

        return response()->json($movements);
    }

    /**
     * Inventory adjustment.
     */
    public function adjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'adjustment' => 'required|integer',
            'reason' => 'required|in:damaged,lost,expired,counting_error,other',
            'notes' => 'nullable|string',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        // Get current total stock
        $currentStock = ProductMovement::where('product_id', $request->product_id)
            ->whereIn('movement_type', ['current', 'stored'])
            ->sum('instock_quantity');

        $adj = InventoryAdjustment::create([
            'product_id' => $request->product_id,
            'quantity_before' => $currentStock,
            'quantity_after' => $currentStock + $request->adjustment,
            'adjustment' => $request->adjustment,
            'reason' => $request->reason,
            'notes' => $request->notes,
            'adjusted_by' => auth('api')->id(),
        ]);

        ActivityLog::log('adjusted', 'Inventory', $adj->id, "Stock adjusted for product #{$request->product_id}: {$request->adjustment} ({$request->reason})");

        return response()->json(['message' => 'Adjustment recorded', 'adjustment' => $adj], 201);
    }
}
