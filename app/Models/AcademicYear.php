<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AcademicYear extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'name', 'start_date', 'end_date',
        'is_current', 'status', 'copied_from_id', 'copied_items',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'is_current'   => 'boolean',
        'copied_items' => 'array',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function copiedFrom()
    {
        return $this->belongsTo(AcademicYear::class, 'copied_from_id');
    }

    /**
     * Scope to get only the current active year for a school.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true)->where('status', 'active');
    }

    /**
     * Check if this year is frozen (read-only archive mode).
     */
    public function isFrozen(): bool
    {
        return $this->status === 'frozen';
    }

    /**
     * Check if this year is active. This acts as an attribute accessor.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }
}
