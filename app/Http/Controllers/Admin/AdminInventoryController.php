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
use OpenApi\Attributes as OA;

class AdminInventoryController extends Controller
{
    #[OA\Get(
        path: '/api/admin/purchases',
        summary: 'List purchase orders and stock-in history with summary statistics',
        tags: ['Admin Inventory'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated purchases and total monthly spending stats'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
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

    #[OA\Post(
        path: '/api/admin/purchases',
        summary: 'Create a new stock-in purchase with batch numbers & expiry dates',
        tags: ['Admin Inventory'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['supplier_id', 'purchase_date', 'items'],
                properties: [
                    new OA\Property(property: 'supplier_id', type: 'integer', example: 1),
                    new OA\Property(property: 'purchase_date', type: 'string', format: 'date', example: '2026-09-20'),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['product_id', 'quantity', 'purchase_price', 'sale_price', 'expired_date', 'batch_number'],
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'integer', example: 100),
                                new OA\Property(property: 'purchase_price', type: 'number', example: 8.50),
                                new OA\Property(property: 'sale_price', type: 'number', example: 12.00),
                                new OA\Property(property: 'expired_date', type: 'string', format: 'date', example: '2027-12-31'),
                                new OA\Property(property: 'manufactured_date', type: 'string', format: 'date', example: '2025-01-15'),
                                new OA\Property(property: 'batch_number', type: 'string', example: 'BATCH-2026-09A'),
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Purchase order and inventory movements created'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
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
            'items.*.batch_number' => 'required|string|max:100',
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
                    'batch_number' => $item['batch_number'],
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

            // Real-time WebSocket Alert for New Purchase
            event(new \App\Events\NewPurchaseAlert($purchase->load('supplier', 'purchaseProducts.productMovement.product')));

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

    #[OA\Get(
        path: '/api/admin/purchases/{id}',
        summary: 'Get purchase order details and line items',
        tags: ['Admin Inventory'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Purchase order details'),
            new OA\Response(response: 404, description: 'Purchase order not found')
        ]
    )]
    public function purchaseShow($id)
    {
        return response()->json(
            Purchase::with(['supplier', 'user', 'purchaseProducts.productMovement.product'])->findOrFail($id)
        );
    }

    #[OA\Get(
        path: '/api/admin/inventory/low-stock',
        summary: 'Get low-stock products with severity breakdown (critical, warning, notice)',
        tags: ['Admin Inventory'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Low stock items and risk counts'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
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
            $latestMovement = $product->movements->first(function($m) {
                return $m->purchaseProduct && $m->purchaseProduct->purchase && $m->purchaseProduct->purchase->supplier;
            }) ?: $product->movements->first();
            
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

    #[OA\Get(
        path: '/api/admin/inventory/expiring',
        summary: 'List batches expiring within next 30 days',
        tags: ['Admin Inventory'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of expiring batches with remaining stock'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function expiringSoon()
    {
        $movements = ProductMovement::with(['product.category', 'product.unit'])
            ->where('instock_quantity', '>', 0)
            ->whereIn('movement_type', ['current', 'stored'])
            ->whereBetween('expired_date', [now(), now()->addDays(30)])
            ->get();

        return response()->json($movements);
    }

    #[OA\Post(
        path: '/api/admin/inventory/adjustments',
        summary: 'Log an inventory count adjustment (damaged, lost, expired, counting_error)',
        tags: ['Admin Inventory'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['product_id', 'adjustment', 'reason', 'notes'],
                properties: [
                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                    new OA\Property(property: 'adjustment', type: 'integer', example: -5, description: 'Positive to add stock, negative to deduct stock'),
                    new OA\Property(property: 'reason', type: 'string', enum: ['damaged', 'lost', 'expired', 'counting_error', 'other'], example: 'damaged'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Dropped carton during shelf restocking'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Inventory adjustment recorded'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function adjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'adjustment' => 'required|integer',
            'reason' => 'required|in:damaged,lost,expired,counting_error,other',
            'notes' => 'required|string|min:5',
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
