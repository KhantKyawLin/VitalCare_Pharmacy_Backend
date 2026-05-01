<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'category_id', 'description', 'usage', 'side_effects',
        'dosage', 'unit_id', 'minimum_quantity', 'reorder_status',
        'is_expired', 'price', 'is_published'
    ];

    public function category() { return $this->belongsTo(Category::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function pictures() { return $this->hasMany(Picture::class); }
    public function primaryPicture() { return $this->hasOne(Picture::class)->where('is_primary', true); }
    public function productHistories() { return $this->hasMany(ProductHistory::class); }
    public function movements() { return $this->hasMany(ProductMovement::class); }
    public function promotions() { return $this->belongsToMany(Promotion::class, 'promotion_products'); }
    public function suppliers() { return $this->belongsToMany(Supplier::class, 'supply_products'); }
}
