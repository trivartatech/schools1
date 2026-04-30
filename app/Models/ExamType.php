<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamType extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'exam_term_id',
        'name',
        'code',
        'display_name',
        'classification',
        'sort_order',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function examTerm()
    {
        return $this->belongsTo(ExamTerm::class);
    }
}
