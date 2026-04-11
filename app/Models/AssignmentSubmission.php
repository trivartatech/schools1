<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model
{
    protected $fillable = [
        'assignment_id',
        'student_id',
        'submitted_at',
        'is_late',
        'content',
        'attachments',
        'marks',
        'remarks',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'is_late'      => 'boolean',
        'attachments'  => 'json',
        'marks'        => 'decimal:2',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
