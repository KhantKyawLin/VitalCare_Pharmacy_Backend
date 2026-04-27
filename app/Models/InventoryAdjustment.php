<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'product_id', 'product_movement_id', 'quantity_before', 'quantity_after',
        'adjustment', 'reason', 'financial_value', 'notes', 'adjusted_by'
    ];

    public function productMovement() { return $this->belongsTo(ProductMovement::class); }

    public function product() { return $this->belongsTo(Product::class); }
    public function adjuster() { return $this->belongsTo(User::class, 'adjusted_by'); }
}
