<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'user_id', 'action', 'model_type', 'model_id',
        'description', 'old_values', 'new_values', 'ip_address'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }

    /**
     * Helper to log an activity.
     */
    public static function log($action, $modelType = null, $modelId = null, $description = null, $oldValues = null, $newValues = null)
    {
        return static::create([
            'user_id' => auth('api')->id(),
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
