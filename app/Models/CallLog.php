<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CallLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'caller_name', 'phone_number',
        'call_type', 'purpose', 'handled_by_id', 'related_student_id',
        'notes', 'follow_up_date', 'follow_up_completed'
    ];

    protected $casts = [
        'follow_up_date' => 'datetime',
        'follow_up_completed' => 'boolean',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function handledBy()
    {
        return $this->belongsTo(Staff::class, 'handled_by_id');
    }

    public function relatedStudent()
    {
        return $this->belongsTo(Student::class, 'related_student_id');
    }
}
