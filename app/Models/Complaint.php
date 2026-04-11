<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'type',
        'raised_by_type', 'raised_by_id',
        'description', 'priority', 'assigned_department_id',
        'assigned_to', 'status', 'resolution_notes', 'resolved_at',
        'attachment_path',
        'sla_hours', 'escalated_at', 'escalation_level', 'sla_breached',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'escalated_at' => 'datetime',
        'sla_breached' => 'boolean',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function raisedBy()
    {
        return $this->morphTo();
    }

    public function assignedDepartment()
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
