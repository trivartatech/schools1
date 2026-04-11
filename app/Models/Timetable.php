<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
    protected $fillable = [
        'school_id',
        'course_class_id',
        'section_id',
        'period_id',
        'day_of_week',
        'subject_id',
        'staff_id'
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function courseClass()
    {
        return $this->belongsTo(CourseClass::class, 'course_class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
