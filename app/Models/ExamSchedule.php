<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    protected $fillable = [
        'school_id', 'academic_year_id', 'exam_type_id', 'course_class_id',
        'weightage',
        'has_co_scholastic', 'scholastic_grading_system_id',
        'co_scholastic_grading_system_id', 'status',
    ];

    protected $casts = [
        'has_co_scholastic' => 'boolean',
        'weightage'         => 'decimal:2',
    ];

    public function school()        { return $this->belongsTo(School::class); }
    public function academicYear()  { return $this->belongsTo(AcademicYear::class); }
    public function examType()      { return $this->belongsTo(ExamType::class); }
    public function courseClass()   { return $this->belongsTo(CourseClass::class); }
    public function scholasticGradingSystem()   { return $this->belongsTo(GradingSystem::class, 'scholastic_grading_system_id'); }
    public function coScholasticGradingSystem() { return $this->belongsTo(GradingSystem::class, 'co_scholastic_grading_system_id'); }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'exam_schedule_sections');
    }

    public function scheduleSubjects()
    {
        return $this->hasMany(ExamScheduleSubject::class)->orderBy('is_co_scholastic')->orderBy('id');
    }
}
