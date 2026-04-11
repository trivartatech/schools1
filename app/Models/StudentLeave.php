<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentLeave extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'student_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'reason',
        'status',
        'approved_by',
        'remarks',
        'applied_by',
        'document_path',
        'document_original_name',
        'document_mime',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * Scope a query to only include records for the current school.
     */
    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    /**
     * Number of days covered by this leave (inclusive).
     */
    public function getDaysCountAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Whether the leave has an attached document.
     */
    public function getHasDocumentAttribute(): bool
    {
        return !empty($this->document_path);
    }

    /**
     * Whether the document is a PDF.
     */
    public function getDocumentIsPdfAttribute(): bool
    {
        return $this->document_mime === 'application/pdf';
    }

    /**
     * Whether the document is an image.
     */
    public function getDocumentIsImageAttribute(): bool
    {
        return str_starts_with($this->document_mime ?? '', 'image/');
    }
}
