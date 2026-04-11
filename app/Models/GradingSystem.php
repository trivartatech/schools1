<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingSystem extends Model
{
    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'type',
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

    public function grades()
    {
        return $this->hasMany(Grade::class)->orderBy('grade_point', 'desc');
    }
}
