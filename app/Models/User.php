<?php

namespace App\Models;

use App\Enums\UserType;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, HasActivityLog;

    protected $fillable = [
        'school_id', 'organization_id',
        'name', 'username', 'email', 'phone',
        'password', 'avatar', 'user_type',
        'is_active', 'fcm_token',
        'expo_push_token', 'device_platform', 'push_token_updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'phone_verified_at'      => 'datetime',
            'last_login_at'          => 'datetime',
            'push_token_updated_at'  => 'datetime',
            'password'               => 'hashed',
            'is_active'              => 'boolean',
            'user_type'              => UserType::class,
        ];
    }

    // ── Scopes ─────────────────────────────────────────────────────

    /**
     * Exclude synthetic photographer users from user listings. Photographers
     * are per-school throwaway logins for ID-card photoshoots — they should
     * never appear in user-management lists, recipient pickers, etc.
     */
    public function scopeExcludingPhotographers($query)
    {
        return $query->where('user_type', '!=', UserType::Photographer);
    }

    // ── Relationships ──────────────────────────────────────────────

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function studentParent()
    {
        return $this->hasOne(StudentParent::class);
    }

    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->user_type === UserType::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return in_array($this->user_type, [UserType::Admin, UserType::SchoolAdmin, UserType::Principal]);
    }

    public function isSchoolAdmin(): bool
    {
        return $this->user_type === UserType::SchoolAdmin;
    }

    public function isPrincipal(): bool
    {
        return $this->user_type === UserType::Principal;
    }

    public function isTeacher(): bool
    {
        return $this->user_type === UserType::Teacher;
    }

    public function isStudent(): bool
    {
        return $this->user_type === UserType::Student;
    }

    public function isParent(): bool
    {
        return $this->user_type === UserType::Parent;
    }

    public function isAccountant(): bool
    {
        return $this->user_type === UserType::Accountant;
    }

    public function isDriver(): bool
    {
        return $this->user_type === UserType::Driver;
    }

    public function isConductor(): bool
    {
        return $this->user_type === UserType::Conductor;
    }

    /** Driver or Conductor — both share the same on-bus mobile flow. */
    public function isBusStaff(): bool
    {
        return in_array($this->user_type, [UserType::Driver, UserType::Conductor]);
    }

    /** School-management level: Admin or Super Admin */
    public function isSchoolManagement(): bool
    {
        return in_array($this->user_type, [UserType::Admin, UserType::SuperAdmin]);
    }

    public function getUserRoleAttribute()
    {
        return $this->roles()->pluck('name')->all();
    }

    public function getUserPermissionAttribute()
    {
        if ($this->isSuperAdmin()) {
            return ['*'];
        }

        return $this->getAllPermissions()->pluck('name')->all();
    }

    /**
     * Helper specifically for ERP permission architecture.
     */
    public function canAccess($permission)
    {
        return $this->hasPermissionTo($permission);
    }
}
