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

    /** PHP date() format string from the admin's system-config date_format setting. */
    public function dateFmt(): string
    {
        return match($this->settings['date_format'] ?? '') {
            'DD/MM/YYYY'  => 'd/m/Y',
            'MM/DD/YYYY'  => 'm/d/Y',
            'YYYY-MM-DD'  => 'Y-m-d',
            'D MMM, YYYY' => 'j M, Y',
            default        => 'd/m/Y',
        };
    }

    /** PHP date() format string from the admin's system-config time_format setting. */
    public function timeFmt(): string
    {
        return match($this->settings['time_format'] ?? '') {
            'h:mm A'    => 'g:i A',
            'H:mm'      => 'H:i',
            'h:mm:ss A' => 'g:i:s A',
            default      => 'g:i A',
        };
    }

    /**
     * Default rows-per-page from System Config. Clamped to [5, 100] to
     * match the SystemConfig form validation. Use with paginate($school->pageLength()).
     */
    public function pageLength(int $fallback = 20): int
    {
        $n = (int) ($this->settings['page_length'] ?? $fallback);
        return max(5, min(100, $n));
    }
}
