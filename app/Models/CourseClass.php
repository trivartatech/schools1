<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CourseClass extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['school_id', 'department_id', 'name', 'numeric_value', 'incharge_staff_id'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Sections scoped to the current academic year via the section_academic_year
     * pivot. Use this when building UI dropdowns so sections created for past
     * years don't leak into the current year's view.
     */
    public function sectionsForCurrentYear()
    {
        $ayId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        return $this->hasMany(Section::class)->when($ayId, fn($q) =>
            $q->whereHas('academicYears', fn($ay) => $ay->where('academic_years.id', $ayId))
        );
    }

    public function inchargeStaff()
    {
        return $this->belongsTo(Staff::class, 'incharge_staff_id');
    }

    public function subjects()
    {
        return $this->hasManyThrough(Subject::class, ClassSubject::class, 'course_class_id', 'id', 'id', 'subject_id')
            ->whereNull('class_subjects.section_id');
    }
}
