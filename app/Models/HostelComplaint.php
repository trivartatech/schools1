<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HostelComplaint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'hostel_id', 'student_id', 'reported_by', 'category', 'title',
        'description', 'location', 'priority', 'status', 'assigned_to',
        'resolution_notes', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function school()     { return $this->belongsTo(School::class); }
    public function hostel()     { return $this->belongsTo(Hostel::class); }
    public function student()    { return $this->belongsTo(Student::class); }
    public function reporter()   { return $this->belongsTo(User::class, 'reported_by'); }
    public function assignee()   { return $this->belongsTo(User::class, 'assigned_to'); }
}
