<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductMovement;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class AdminExpiredItemController extends Controller
{
    #[OA\Get(
        path: '/api/admin/inventory/expired-items',
        summary: 'Get currently expired batches with financial value at risk',
        tags: ['Admin Inventory'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Expired items list and monthly loss stats'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function expired()
    {
        $movements = ProductMovement::with(['product.category', 'product.unit', 'purchaseProduct.purchase.supplier'])
            ->where('instock_quantity', '>', 0)
            ->whereIn('movement_type', ['current', 'stored'])
            ->where('expired_date', '<', today())
            ->get();

        $disposalsThisMonth = InventoryAdjustment::whereIn('reason', ['expired', 'returned_to_supplier'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('adjustment');

        $valueAtRisk = $movements->sum(function ($m) {
            return $m->instock_quantity * $m->purchase_price;
        });

        // Expiring Soon (30 days default)
        $expiringSoonMovements = ProductMovement::where('instock_quantity', '>', 0)
            ->whereIn('movement_type', ['current', 'stored'])
            ->whereBetween('expired_date', [today(), today()->addDays(30)])
            ->get();

        $expiringSoonValue = $expiringSoonMovements->sum(function ($m) {
            return $m->instock_quantity * $m->purchase_price;
        });

        return response()->json([
            'expired_items' => $movements,
            'stats' => [
                'currently_expired' => $movements->count(),
                'currently_expired_qty' => $movements->sum('instock_quantity'),
                'expiring_soon_count' => $expiringSoonMovements->count(),
                'disposals_this_month' => abs($disposalsThisMonth),
                'value_at_risk' => $valueAtRisk + $expiringSoonValue
            ]
        ]);
    }

    #[OA\Get(
        path: '/api/admin/inventory/expiring-soon-configured',
        summary: 'Get batches expiring within a customizable day range',
        tags: ['Admin Inventory'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'days', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 30, example: 60))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of expiring batches'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function expiringSoon(Request $request)
    {
        $days = $request->query('days', 30); // Default to 30 days
        
        $movements = ProductMovement::with(['product.category', 'product.unit', 'purchaseProduct.purchase.supplier'])
            ->where('instock_quantity', '>', 0)
            ->whereIn('movement_type', ['current', 'stored'])
            ->whereBetween('expired_date', [today(), today()->addDays((int)$days)])
            ->orderBy('expired_date', 'asc')
            ->get();

        return response()->json([
            'expiring_soon' => $movements,
            'count' => $movements->count(),
            'total_qty' => $movements->sum('instock_quantity'),
            'days_range' => $days
        ]);
    }

    #[OA\Post(
        path: '/api/admin/inventory/expired-items/dispose',
        summary: 'Dispose or return expired batches to supplier',
        tags: ['Admin Inventory'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['movement_ids', 'reason'],
                properties: [
                    new OA\Property(property: 'movement_ids', type: 'array', items: new OA\Items(type: 'integer', example: 10)),
                    new OA\Property(property: 'reason', type: 'string', enum: ['expired', 'returned_to_supplier'], example: 'expired'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Items disposed and written off'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function dispose(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'movement_ids' => 'required|array|min:1',
            'movement_ids.*' => 'exists:product_movements,id',
            'reason' => 'required|in:expired,returned_to_supplier'
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        DB::beginTransaction();
        try {
            $disposedCount = 0;
            $totalLoss = 0;

            foreach ($request->movement_ids as $id) {
                $movement = ProductMovement::lockForUpdate()->find($id);

                if ($movement && $movement->instock_quantity > 0) {
                    $qty = $movement->instock_quantity;
                    $value = $qty * $movement->purchase_price;

                    // Get current global stock for this product
                    $currentStock = ProductMovement::where('product_id', $movement->product_id)
                        ->whereIn('movement_type', ['current', 'stored'])
                        ->sum('instock_quantity');

                    InventoryAdjustment::create([
                        'product_id' => $movement->product_id,
                        'product_movement_id' => $movement->id,
                        'quantity_before' => $currentStock,
                        'quantity_after' => $currentStock - $qty,
                        'adjustment' => -$qty,
                        'reason' => $request->reason,
                        'financial_value' => $value,
                        'notes' => $request->reason === 'returned_to_supplier' 
                            ? 'Returned to supplier via inventory management' 
                            : 'Disposed from expired management module',
                        'adjusted_by' => auth('api')->id(),
                    ]);

                    $movement->update([
                        'instock_quantity' => 0,
                        'movement_type' => 'sold-out' // Meaning no longer active
                    ]);

                    $disposedCount++;
                    $totalLoss += $value;
                }
            }

            DB::commit();

            $actionText = $request->reason === 'returned_to_supplier' ? 'Returned' : 'Disposed';
            ActivityLog::log('adjusted', 'Inventory', null, "{$actionText} {$disposedCount} batches with value Tk {$totalLoss}");

            return response()->json([
                'message' => 'Items disposed successfully.',
                'disposed_count' => $disposedCount,
                'total_loss' => $totalLoss
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to dispose: ' . $e->getMessage()], 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/inventory/disposals',
        summary: 'List history of expired/returned disposal write-offs',
        tags: ['Admin Inventory'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of disposal logs'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function disposals()
    {
        $disposals = InventoryAdjustment::with(['product.category', 'product.unit', 'adjuster', 'productMovement.purchaseProduct.purchase.supplier'])
            ->whereIn('reason', ['expired', 'returned_to_supplier'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($disposals);
    }
}
