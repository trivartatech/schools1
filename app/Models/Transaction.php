<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'transaction_no',
        'date',
        'type',           // journal | receipt | payment | contra
        'status',         // draft | posted | void
        'reversal_of',    // nullable FK → transactions.id
        'narration',
        'reference_no',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function school()       { return $this->belongsTo(School::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function lines()        { return $this->hasMany(TransactionLine::class); }
    public function createdBy()    { return $this->belongsTo(User::class, 'created_by'); }
    public function reversalOf()   { return $this->belongsTo(Transaction::class, 'reversal_of'); }

    // ── Computed attributes ───────────────────────────────────
    public function getTotalAmountAttribute(): float
    {
        return (float) $this->lines()->where('type', 'debit')->sum('amount');
    }

    public function getIsBalancedAttribute(): bool
    {
        $debit  = (float) $this->lines()->where('type', 'debit')->sum('amount');
        $credit = (float) $this->lines()->where('type', 'credit')->sum('amount');
        return abs($debit - $credit) < 0.01;
    }

    public function getIsReversedAttribute(): bool
    {
        return static::where('reversal_of', $this->id)->exists();
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeTenant($query)
    {
        return $query->where('school_id', app('current_school_id'));
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    // ── Race-condition-safe transaction number generator ──────
    /**
     * Generates the next unique transaction_no for a school.
     * Uses an exclusive row lock on the School record to serialise
     * concurrent requests, guaranteeing sequential numbering.
     *
     * Format: TXN-{YEAR}-{NNNN}  e.g. TXN-2026-0042
     */
    public static function generateNo(int $schoolId): string
    {
        $year   = date('Y');
        $prefix = 'TXN-' . $year . '-';

        $candidate = DB::transaction(function () use ($schoolId, $prefix) {
            School::where('id', $schoolId)->lockForUpdate()->first();

            // Must include soft-deleted rows — the DB unique constraint covers them
            return static::withTrashed()
                ->where('school_id', $schoolId)
                ->where('transaction_no', 'like', $prefix . '%')
                ->count() + 1;
        });

        // Safety loop: skip any number already in use (including soft-deleted)
        do {
            $no     = $prefix . str_pad($candidate, 4, '0', STR_PAD_LEFT);
            $exists = static::withTrashed()
                ->where('school_id', $schoolId)
                ->where('transaction_no', $no)
                ->exists();
            if ($exists) {
                $candidate++;
            }
        } while ($exists);

        return $no;
    }
}
