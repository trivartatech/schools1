<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'class_id', 'section_id', 'subject_id', 'teacher_id',
        'title', 'description', 'due_date', 'max_marks', 'status', 'attachments',
    ];

    protected $casts = [
        'due_date'    => 'datetime',
        'attachments' => 'json',
    ];

    public function school()       { return $this->belongsTo(School::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function courseClass()  { return $this->belongsTo(CourseClass::class, 'class_id'); }
    public function section()      { return $this->belongsTo(Section::class); }
    public function subject()      { return $this->belongsTo(Subject::class); }
    public function teacher()      { return $this->belongsTo(Staff::class, 'teacher_id'); }
    public function submissions()  { return $this->hasMany(AssignmentSubmission::class); }

    public function getIsExpiredAttribute(): bool
    {
        return $this->due_date->isPast();
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'published' && !$this->is_expired;
    }
}
