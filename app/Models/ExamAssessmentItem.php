<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAssessmentItem extends Model
{
    protected $fillable = [
        'school_id',
        'exam_assessment_id',
        'name',
        'code',
        'sort_order',
    ];

    public function examAssessment()
    {
        return $this->belongsTo(ExamAssessment::class);
    }
}
