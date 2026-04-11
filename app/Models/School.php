<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'slug', 'code',
        'logo', 'favicon', 'board', 'affiliation_no', 'udise_code',
        'address', 'city', 'state', 'pincode', 'phone', 'email',
        'website', 'principal_name', 'timezone', 'currency',
        'language', 'status', 'features', 'settings',
        'geo_fence_lat', 'geo_fence_lng', 'geo_fence_radius',
    ];

    protected $casts = [
        'features' => 'array',
        'settings' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function academicYears()
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function currentAcademicYear()
    {
        return $this->hasOne(AcademicYear::class)->where('is_current', true);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function students()
    {
        return $this->hasMany(User::class)->where('user_type', 'student');
    }

    /**
     * Check if a given feature is enabled for this school.
     */
    public function hasFeature(string $feature): bool
    {
        return isset($this->features[$feature]) && $this->features[$feature] === true;
    }
}
