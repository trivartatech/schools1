<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentHealthRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id', 'student_id',
        'height_cm', 'weight_kg',
        'vision_left', 'vision_right', 'hearing',
        'known_allergies', 'chronic_conditions', 'current_medications',
        'past_surgeries', 'disability', 'special_needs',
        'vaccinations',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'family_doctor_name', 'family_doctor_phone',
        'remarks',
    ];

    protected $casts = [
        'vaccinations' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
