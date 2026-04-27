<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['name', 'phone', 'address'];

    public function supplyProducts()
    {
        return $this->hasMany(SupplyProduct::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'supply_products');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
