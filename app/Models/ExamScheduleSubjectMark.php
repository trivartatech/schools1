<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamScheduleSubjectMark extends Model
{
    protected $fillable = [
        'exam_schedule_subject_id', 'exam_assessment_item_id', 'max_marks', 'passing_marks',
    ];

    public function examScheduleSubject() { return $this->belongsTo(ExamScheduleSubject::class); }
    public function examAssessmentItem()  { return $this->belongsTo(ExamAssessmentItem::class); }
}
