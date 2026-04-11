<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAssessment extends Model
{
    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'description',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function items()
    {
        return $this->hasMany(ExamAssessmentItem::class)->orderBy('sort_order');
    }
}
