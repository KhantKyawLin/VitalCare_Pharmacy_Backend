<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderProductBatch extends Model
{
    protected $fillable = ['order_product_id', 'product_movement_id', 'quantity'];

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class);
    }

    public function productMovement()
    {
        return $this->belongsTo(ProductMovement::class);
    }
}
