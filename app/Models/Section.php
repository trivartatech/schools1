<?php

namespace App\Models;

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
}
