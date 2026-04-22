<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['school_id', 'course_class_id', 'name', 'capacity', 'sort_order', 'incharge_staff_id'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function courseClass()
    {
        return $this->belongsTo(CourseClass::class);
    }

    public function inchargeStaff()
    {
        return $this->belongsTo(Staff::class, 'incharge_staff_id');
    }

    public function classSubjects()
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function subjects()
    {
        return $this->hasManyThrough(Subject::class, ClassSubject::class, 'section_id', 'id', 'id', 'subject_id');
    }

    /**
     * Academic years this section is active in (via section_academic_year pivot).
     */
    public function academicYears()
    {
        return $this->belongsToMany(AcademicYear::class, 'section_academic_year')->withTimestamps();
    }

    /**
     * Scope: only sections attached to the given academic year via the pivot.
     * Pass null to no-op (useful when year binding is not available).
     */
    public function scopeForYear(Builder $query, ?int $academicYearId): Builder
    {
        if ($academicYearId) {
            $query->whereHas('academicYears', fn($ay) => $ay->where('academic_years.id', $academicYearId));
        }
        return $query;
    }

    /**
     * Scope: only sections attached to the current academic year (from container
     * binding). No-ops outside of the request lifecycle.
     */
    public function scopeForCurrentYear(Builder $query): Builder
    {
        $ayId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        return $query->forYear($ayId);
    }
}
