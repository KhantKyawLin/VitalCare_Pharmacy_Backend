<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HealthTip extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'content', 'image_path', 'user_id', 'is_published'];

    public function author() { return $this->belongsTo(User::class, 'user_id'); }
    public function feedbacks() { return $this->hasMany(Feedback::class); }

    public function averageRating()
    {
        return $this->feedbacks()->avg('rating');
    }
}
