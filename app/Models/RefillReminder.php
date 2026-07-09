<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefillReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_product_id',
        'product_id',
        'reminder_date',
        'status',
        'due_date',
        'notes',
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'due_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
