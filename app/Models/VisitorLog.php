<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'name', 'phone', 'purpose',
        'person_to_meet_type', 'person_to_meet_id', 'id_proof_path',
        'in_time', 'out_time', 'photo_path', 'notes',
        'is_pre_registered', 'expected_date', 'expected_time',
        'pre_registered_by', 'badge_number', 'id_type', 'id_number',
    ];

    protected $casts = [
        'in_time' => 'datetime',
        'out_time' => 'datetime',
        'is_pre_registered' => 'boolean',
        'expected_date' => 'date',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function personToMeet()
    {
        return $this->morphTo();
    }
}
