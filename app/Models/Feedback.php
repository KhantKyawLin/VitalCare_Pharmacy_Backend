<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedbacks';
    protected $fillable = ['user_id', 'health_tip_id', 'comments', 'rating'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function healthTip()
    {
        return $this->belongsTo(HealthTip::class);
    }
}
