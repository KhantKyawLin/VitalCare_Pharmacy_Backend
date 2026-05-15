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
        'is_expired', 'price', 'is_published', 'requires_prescription'
    ];

    protected $appends = ['primary_image_url'];

    public function getPrimaryImageUrlAttribute()
    {
        $pic = $this->primaryPicture;
        if (!$pic) {
            $pic = $this->pictures()->first();
        }
        return $pic ? "http://127.0.0.1:8000/storage/" . $pic->image_path : null;
    }

    public function category() { return $this->belongsTo(Category::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function pictures() { return $this->hasMany(Picture::class); }
    public function primaryPicture() { return $this->hasOne(Picture::class)->where('is_primary', true); }
    public function productHistories() { return $this->hasMany(ProductHistory::class); }
    public function movements() { return $this->hasMany(ProductMovement::class); }
    public function promotions() { return $this->belongsToMany(Promotion::class, 'promotion_products'); }
    public function suppliers() { return $this->belongsToMany(Supplier::class, 'supply_products'); }
    public function orderProducts() { return $this->hasMany(OrderProduct::class); }
    public function latestMovement()
    {
        return $this->hasOne(ProductMovement::class)
            ->whereIn('movement_type', ['current', 'stored'])
            ->where('purchase_price', '>', 0)
            ->latest('id');
    }

    /**
     * Filter products that are not expired based on their latest movement.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function($q) {
            $q->whereHas('latestMovement', function($sub) {
                $sub->where(function($inner) {
                    $inner->whereNull('expired_date')
                          ->orWhere('expired_date', '>=', now()->startOfDay());
                });
            })->orWhereDoesntHave('latestMovement');
        });
    }

    /**
     * Get the price of the product, applying 10% markup if price is 0.
     * Optimized to use eager-loaded relation if available.
     */
    public function getEffectivePrice()
    {
        if ($this->price && (float)$this->price > 0) {
            return (float)$this->price;
        }

        $latestMovement = $this->relationLoaded('latestMovement')
            ? $this->latestMovement
            : $this->latestMovement()->first();

        if ($latestMovement) {
            return (float)$latestMovement->purchase_price * 1.10;
        }

        return 0;
    }
}
