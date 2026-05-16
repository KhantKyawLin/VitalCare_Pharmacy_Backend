<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'quantity', 'price', 'original_price', 'purchase_price', 'is_gift'
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function batches() { return $this->hasMany(OrderProductBatch::class); }
}
