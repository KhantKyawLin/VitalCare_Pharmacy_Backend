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

class AdminExpiredItemController extends Controller
{
    /**
     * Get currently expired items with stock.
     */
    public function expired()
    {
        $movements = ProductMovement::with(['product.category', 'product.unit'])
            ->where('instock_quantity', '>', 0)
            ->whereIn('movement_type', ['current', 'stored'])
            ->where('expired_date', '<', today())
            ->get();

        $disposalsThisMonth = InventoryAdjustment::where('reason', 'expired')
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

    /**
     * Get items expiring soon (configurable days).
     */
    public function expiringSoon(Request $request)
    {
        $days = $request->query('days', 30); // Default to 30 days
        
        $movements = ProductMovement::with(['product.category', 'product.unit'])
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

    /**
     * Dispose of selected expired items.
     */
    public function dispose(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'movement_ids' => 'required|array|min:1',
            'movement_ids.*' => 'exists:product_movements,id'
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
                        'reason' => 'expired',
                        'financial_value' => $value,
                        'notes' => 'Disposed from expired management module',
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

            ActivityLog::log('adjusted', 'Inventory', null, "Disposed {$disposedCount} expired batches with value Tk {$totalLoss}");

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

    /**
     * Get disposal records.
     */
    public function disposals()
    {
        $disposals = InventoryAdjustment::with(['product.category', 'product.unit', 'adjuster', 'productMovement'])
            ->where('reason', 'expired')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($disposals);
    }
}
