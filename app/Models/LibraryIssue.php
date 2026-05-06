<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LibraryIssue extends Model
{
    protected $fillable = [
        'school_id', 'book_id', 'student_id', 'staff_id', 'borrower_type',
        'issue_date', 'due_date', 'return_date', 'status',
        'fine_amount', 'fine_paid', 'issued_by', 'returned_to', 'notes',
    ];

    protected $appends = ['borrower_name', 'calculated_fine'];

    protected $casts = [
        'issue_date'  => 'date',
        'due_date'    => 'date',
        'return_date' => 'date',
        'fine_amount' => 'float',
        'fine_paid'   => 'boolean',
    ];

    public function school()     { return $this->belongsTo(School::class); }
    public function book()       { return $this->belongsTo(LibraryBook::class, 'book_id'); }
    public function student()    { return $this->belongsTo(Student::class); }
    public function staff()      { return $this->belongsTo(Staff::class); }
    public function issuedBy()   { return $this->belongsTo(User::class, 'issued_by'); }
    public function returnedTo() { return $this->belongsTo(User::class, 'returned_to'); }

    public function getBorrowerNameAttribute(): string
    {
        if ($this->borrower_type === 'student') {
            return $this->student?->name ?? '—';
        }
        return $this->staff?->user?->name ?? '—';
    }

    public function getCalculatedFineAttribute(): float
    {
        if ($this->status === 'returned' || $this->return_date) return (float) $this->fine_amount;
        if (now()->gt($this->due_date)) {
            $settings = LibrarySetting::where('school_id', $this->school_id)->first();
            $ratePerDay = $settings?->fine_per_day ?? 1.00;
            return round(now()->diffInDays($this->due_date) * $ratePerDay, 2);
        }
        return 0;
    }
}
