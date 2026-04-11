<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferCertificate extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'school_id',
        'student_id',
        'certificate_no',
        'status',
        'leaving_date',
        'reason',
        'conduct',
        'last_class_studied',
        'fee_paid_upto',
        'has_dues',
        'remarks',
        'requested_by',
        'approved_by',
        'approved_at',
        'issued_at',
    ];

    protected $casts = [
        'leaving_date'   => 'date',
        'fee_paid_upto'  => 'date',
        'has_dues'       => 'boolean',
        'approved_at'    => 'datetime',
        'issued_at'      => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Helpers ───────────────────────────────────────────────

    public function isRequested(): bool { return $this->status === 'requested'; }
    public function isApproved(): bool  { return $this->status === 'approved';  }
    public function isIssued(): bool    { return $this->status === 'issued';    }
    public function isRejected(): bool  { return $this->status === 'rejected';  }
}
