<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamScheduleSubject extends Model
{
    protected $fillable = [
        'exam_schedule_id', 'subject_id', 'exam_assessment_id', 'grading_system_id',
        'is_co_scholastic', 'is_enabled',
        'exam_date', 'exam_time', 'duration_minutes',
    ];

    protected $casts = [
        'is_co_scholastic' => 'boolean',
        'is_enabled'       => 'boolean',
    ];

    public function examSchedule()   { return $this->belongsTo(ExamSchedule::class); }
    public function subject()        { return $this->belongsTo(Subject::class); }
    public function examAssessment() { return $this->belongsTo(ExamAssessment::class); }
    public function markConfigs()    { return $this->hasMany(ExamScheduleSubjectMark::class); }
    public function examMarks()      { return $this->hasMany(ExamMark::class, 'exam_schedule_subject_id'); }
    public function gradingSystem()  { return $this->belongsTo(GradingSystem::class); }
}
