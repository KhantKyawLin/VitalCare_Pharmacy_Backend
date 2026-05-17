<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductMovement;
use Illuminate\Support\Facades\DB;

class AllocateFefoStockAction
{
    /**
     * Determine which FEFO batches to consume for a given quantity requirement.
     *
     * @param Product $product
     * @param int $quantity
     * @return array
     */
    public function execute(Product $product, int $quantity): array
    {
        $batches = ProductMovement::where('product_id', $product->id)
            ->whereIn('movement_type', ['current', 'stored'])
            ->where('expired_date', '>=', now())
            ->orderBy('expired_date', 'asc')
            ->get();

        $qtyNeeded = $quantity;
        $fefoBatchesToConsume = [];
        $totalAvailable = 0;

        foreach ($batches as $batch) {
            $consumed = DB::table('order_product_batches')->where('product_movement_id', $batch->id)->sum('quantity');
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

        return [
            'batches' => $fefoBatchesToConsume,
            'available' => $totalAvailable,
            'unmet_quantity' => $qtyNeeded
        ];
    }
}
