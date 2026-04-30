<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'display_name',
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

    public function examTypes()
    {
        return $this->hasMany(ExamType::class);
    }
}
