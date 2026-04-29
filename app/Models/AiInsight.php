<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInsight extends Model
{
    protected $fillable = [
        'school_id',
        'academic_year_id',
        'snapshot_date',
        'range_from',
        'range_to',
        'snapshot_json',
        'insights_json',
        'generated_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'range_from'    => 'date',
        'range_to'      => 'date',
        'snapshot_json' => 'array',
        'insights_json' => 'array',
        'generated_at'  => 'datetime',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
