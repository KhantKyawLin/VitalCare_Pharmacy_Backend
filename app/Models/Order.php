<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'address_id', 'total_amount', 'slip_image', 'status',
        'order_type', 'discount_amount', 'tax_amount', 'received_amount',
        'change_return', 'receipt_number', 'cashier_id'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'change_return' => 'decimal:2',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function cashier() { return $this->belongsTo(User::class, 'cashier_id'); }
    public function orderProducts() { return $this->hasMany(OrderProduct::class); }
}
