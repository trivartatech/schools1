<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClassSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id', 'course_class_id', 'section_id', 'subject_id', 'is_co_scholastic', 'incharge_staff_id',
    ];

    protected $casts = [
        'is_co_scholastic' => 'boolean',
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

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function inchargeStaff()
    {
        return $this->belongsTo(Staff::class, 'incharge_staff_id');
    }
}
