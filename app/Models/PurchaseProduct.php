<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PurchaseProduct extends Model
{
    public $timestamps = false;
    protected $fillable = ['purchase_id', 'product_movement_id', 'purchase_quantity'];

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function productMovement() { return $this->belongsTo(ProductMovement::class); }
}
