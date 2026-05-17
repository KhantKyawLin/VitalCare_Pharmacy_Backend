<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductMovement;
use Illuminate\Support\Collection;

class ApplyPromotionsAction
{
    /**
     * Compute prices and free gift clones based on active promotions.
     *
     * @param Product $product
     * @param int $quantity
     * @param Collection $products
     * @return array
     */
    public function execute(Product $product, int $quantity, Collection $products): array
    {
        $standardPrice = $product->getEffectivePrice();
        $finalPrice = $standardPrice;
        $promo = $product->promotions->first();
        
        $items = [];
        
        if ($promo) {
            $val = (float)$promo->discount_value;
            if ($promo->type === 'percentage') {
                $finalPrice = $standardPrice - ($standardPrice * ($val / 100));
            } elseif ($promo->type === 'fixed_amount') {
                $finalPrice = max(0, $standardPrice - $val);
            }
        }

        // Get latest purchase price for ledger tracking
        $latestBatch = ProductMovement::where('product_id', $product->id)
            ->whereIn('movement_type', ['current', 'stored'])
            ->where('instock_quantity', '>', 0)
            ->orderBy('movement_date', 'desc')
            ->first();
        $costPrice = $latestBatch ? $latestBatch->purchase_price : 0;

        // Main line item
        $items[] = [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $finalPrice,
            'original_price' => $standardPrice,
            'purchase_price' => $costPrice,
            'is_gift' => false,
        ];

        // Free Gifts/BOGO calculations
        if ($promo) {
            if ($promo->type === 'buy_one_get_one') {
                $giftQty = $quantity;
                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $giftQty,
                    'price' => 0,
                    'original_price' => $standardPrice,
                    'purchase_price' => $costPrice,
                    'is_gift' => true,
                ];
            } elseif ($promo->type === 'buy_one_get_gift' && $promo->gift_product_id) {
                $giftProduct = $products->get($promo->gift_product_id);
                if ($giftProduct) {
                    $giftQty = $quantity * ($promo->gift_qty ?: 1);
                    $items[] = [
                        'product_id' => $giftProduct->id,
                        'quantity' => $giftQty,
                        'price' => 0,
                        'original_price' => $giftProduct->price,
                        'purchase_price' => 0,
                        'is_gift' => true,
                    ];
                }
            }
        }

        return $items;
    }
}
