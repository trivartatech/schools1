<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentDiary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'class_id', 'section_id', 'subject_id',
        'teacher_id', 'date', 'content', 'attachments',
    ];

    protected $casts = [
        'date'        => 'date',
        'attachments' => 'json',
    ];

    public function school()       { return $this->belongsTo(School::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function courseClass()  { return $this->belongsTo(CourseClass::class, 'class_id'); }
    public function section()      { return $this->belongsTo(Section::class); }
    public function subject()      { return $this->belongsTo(Subject::class); }
    public function teacher()      { return $this->belongsTo(Staff::class, 'teacher_id'); }
    public function reads()        { return $this->hasMany(DiaryRead::class, 'diary_id'); }
    public function completions()  { return $this->hasMany(DiaryCompletion::class, 'diary_id'); }
}
