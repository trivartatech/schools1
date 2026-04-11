<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $fillable = [
        'school_id',
        'grading_system_id',
        'name',
        'min_percentage',
        'max_percentage',
        'grade_point',
        'description',
        'color_code',
        'is_fail',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function gradingSystem()
    {
        return $this->belongsTo(GradingSystem::class);
    }
}
