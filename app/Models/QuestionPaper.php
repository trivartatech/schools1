<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionPaper extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'class_id', 'subject_id',
        'title', 'exam_type', 'total_marks', 'duration_minutes',
        'difficulty', 'instructions', 'created_by',
    ];

    public function school()       { return $this->belongsTo(School::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function courseClass()   { return $this->belongsTo(CourseClass::class, 'class_id'); }
    public function subject()      { return $this->belongsTo(Subject::class); }
    public function createdBy()    { return $this->belongsTo(User::class, 'created_by'); }
    public function sections()     { return $this->hasMany(QuestionPaperSection::class)->orderBy('sort_order'); }
}
