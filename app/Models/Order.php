<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'order_date', 'total_order_amount', 'delivery_address',
        'order_status', 'deliver_status', 'payment_method',
        'payment_status', 'payment_screenshot'
    ];

    protected $casts = ['order_date' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function orderProducts() { return $this->hasMany(OrderProduct::class); }
}
