<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamMark extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'school_id',
        'academic_year_id',
        'student_id',
        'exam_schedule_subject_id',
        'exam_assessment_item_id',
        'marks_obtained',
        'is_absent',
        'teacher_remarks',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function examScheduleSubject()
    {
        return $this->belongsTo(ExamScheduleSubject::class);
    }

    public function assessmentItem()
    {
        return $this->belongsTo(ExamAssessmentItem::class, 'exam_assessment_item_id');
    }
}
