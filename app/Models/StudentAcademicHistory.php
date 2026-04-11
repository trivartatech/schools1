<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentAcademicHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'student_id', 'academic_year_id',
        'class_id', 'section_id', 'roll_no', 'status', 'enrollment_type', 'student_type', 'remarks'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseClass()
    {
        return $this->belongsTo(CourseClass::class, 'class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
