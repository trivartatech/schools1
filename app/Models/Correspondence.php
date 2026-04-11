<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Correspondence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'type', 'reference_number',
        'sender_receiver_name', 'subject', 'department_id', 'date',
        'attachment_path', 'dispatch_tracking', 'courier_name', 'notes',
        'acknowledged', 'acknowledged_at', 'acknowledged_by', 'delivery_status',
    ];

    protected $casts = [
        'date' => 'date',
        'acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
