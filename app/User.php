<?php

namespace App;

use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements CanResetPasswordContract
{
    use Notifiable, CanResetPassword;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'phone',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

<<<<<<< Updated upstream
    public function isAdmin()
    {
        return strtoupper(trim((string) $this->role)) === 'ADMIN';
    }

    public function hasPermission($module, $action = 'view')
    {
        if ($this->isAdmin()) {
            return true;
=======
    public function canManageRoles()
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('role_user')) {
            return strtolower((string) $this->role) === 'admin';
>>>>>>> Stashed changes
        }

        if ($this->roles()->exists()) {
            return $this->roles()->whereIn('name', ['ADMIN', 'OWNER'])->exists();
        }

        return strtolower((string) $this->role) === 'admin';
    }

    public function hasPermission($module, $action = 'view')
    {
        if (!$this->exists || !\Illuminate\Support\Facades\Schema::hasTable('role_user')) {
            if (strtolower((string) $this->role) === 'admin') {
                return true;
            }

            return false;
        }

        if (!$this->roles()->exists() && strtolower((string) $this->role) === 'admin') {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($module, $action) {
                $query->where('module', $module)->where('action', $action);
            })
            ->exists();
    }
}
