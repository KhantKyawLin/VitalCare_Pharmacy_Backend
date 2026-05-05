<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'profile', 'name', 'email', 'password',
        'role', 'role_id', 'gender', 'phone', 'address', 'must_change_password'
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [];
    }

    // --- Relationships ---

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    public function healthTips()
    {
        return $this->hasMany(HealthTip::class);
    }

    public function passwordResetRequests()
    {
        return $this->hasMany(PasswordResetRequest::class);
    }

    // --- RBAC Helpers ---

    public function hasPermission($permissionName)
    {
        // Superadmin & admin have all permissions
        if (in_array($this->role, ['superadmin', 'admin'])) {
            return true;
        }

        // Check RBAC table for staff/pharmacist
        if ($this->roleModel) {
            return $this->roleModel->hasPermission($permissionName);
        }

        return false;
    }

    public function isAdmin()
    {
        return in_array($this->role, ['superadmin', 'admin']);
    }

    public function isStaff()
    {
        return $this->role === 'staff';
    }

    public function isPharmacist()
    {
        return $this->role === 'pharmacist';
    }

    // --- JWT ---

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'name' => $this->name
        ];
    }
}
