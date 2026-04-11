<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'code',
        'days_allowed',
        'color',
        'is_paid',
        'carry_forward',
        'max_carry_forward_days',
        'requires_document',
        'min_notice_days',
        'is_active',
        'description',
        'sort_order',
        'applicable_to',
    ];

    protected $casts = [
        'is_paid'           => 'boolean',
        'carry_forward'     => 'boolean',
        'requires_document' => 'boolean',
        'is_active'         => 'boolean',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function studentLeaves()
    {
        return $this->hasMany(StudentLeave::class);
    }

    /**
     * Count usages: student leave types count student_leaves, staff types count leaves.
     */
    public function getUsageCountAttribute(): int
    {
        return $this->applicable_to === 'student'
            ? $this->studentLeaves()->count()
            : $this->leaves()->count();
    }

    /** Scope to leave types usable for staff (applicable_to = staff|both). */
    public function scopeForStaff($query)
    {
        return $query->whereIn('applicable_to', ['staff', 'both']);
    }

    /** Scope to leave types usable for students (applicable_to = student|both). */
    public function scopeForStudents($query)
    {
        return $query->whereIn('applicable_to', ['student', 'both']);
    }
}
