<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductMovement extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'product_id', 'supply_product_id', 'instock_quantity',
        'manufactured_date', 'expired_date', 'movement_type', 'batch_number',
        'purchase_price', 'sale_price', 'movement_date', 'created_by'
    ];

    protected $casts = [
        'manufactured_date' => 'date',
        'expired_date' => 'date',
        'movement_date' => 'datetime',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function supplyProduct() { return $this->belongsTo(SupplyProduct::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function purchaseProduct() { return $this->hasOne(PurchaseProduct::class, 'product_movement_id'); }
}
