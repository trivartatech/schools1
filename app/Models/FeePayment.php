<?php

namespace App\Models;

use App\Enums\FeePaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeePayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'receipt_no', 'school_id', 'student_id', 'academic_year_id',
        'fee_head_id', 'fee_structure_id', 'fee_structure_snapshot',
        'concession_id', 'concession_note', 'amount_due', 'amount_paid', 'discount', 'fine',
        'balance', 'term', 'payment_date', 'payment_mode',
        'transaction_ref', 'status', 'remarks', 'collected_by',
        'taxable_amount', 'tax_amount', 'tax_percent',
        'gl_transaction_id',
        'is_carry_forward', 'source_payment_id', 'source_year_id', 'rollover_run_id',
    ];

    protected $casts = [
        'amount_due'     => 'decimal:2',
        'amount_paid'    => 'decimal:2',
        'discount'       => 'decimal:2',
        'fine'           => 'decimal:2',
        'balance'        => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'tax_percent'    => 'decimal:2',
        'payment_date'           => 'date:Y-m-d',
        'status'                 => FeePaymentStatus::class,
        // payment_mode is intentionally NOT cast to App\Enums\PaymentMode —
        // admins can register custom modes (phonepe, paytm, gpay, wallet, …)
        // via Finance → Payment Methods. The enum has only 9 fixed cases;
        // casting would throw ValueError on save for any custom code that
        // passed the dynamic-list validation. Read-side callers (PDF blade,
        // MobileApiController) handle both string and enum via instanceof.
        'fee_structure_snapshot' => 'array',
        'is_carry_forward'       => 'boolean',
    ];

    public function student()       { return $this->belongsTo(Student::class); }
    public function feeHead()       { return $this->belongsTo(FeeHead::class); }
    public function feeStructure()  { return $this->belongsTo(FeeStructure::class)->withTrashed(); }
    public function collectedBy()   { return $this->belongsTo(User::class, 'collected_by'); }
    public function glTransaction() { return $this->belongsTo(Transaction::class, 'gl_transaction_id'); }
    public function academicYear()  { return $this->belongsTo(AcademicYear::class); }
    public function school()        { return $this->belongsTo(School::class); }
    public function sourcePayment() { return $this->belongsTo(self::class, 'source_payment_id'); }
    public function sourceYear()    { return $this->belongsTo(AcademicYear::class, 'source_year_id'); }
    public function rolloverRun()   { return $this->belongsTo(RolloverRun::class); }

    // Auto-generate receipt number using school's configurable format
    protected static function booted(): void
    {
        static::creating(function (self $payment) {
            if (empty($payment->receipt_no)) {
                // Load school settings for custom receipt format
                $school   = \App\Models\School::find($payment->school_id);
                $settings = $school?->settings ?? [];

                $prefix    = $settings['fee_receipt_prefix']    ?? 'FEE-';
                $suffix    = $settings['fee_receipt_suffix']    ?? '';
                $startNo   = (int) ($settings['fee_receipt_start_no']   ?? 1);
                $padLength = (int) ($settings['fee_receipt_pad_length']  ?? 5);

                // Resolve dynamic date/year tokens in prefix/suffix
                $prefix = self::resolveTokens($prefix, $school);
                $suffix = self::resolveTokens($suffix, $school);

                // ── Race-condition-safe receipt number generation ───────────────
                // Strategy: lock the School row so only one transaction at a time
                // can read the count and advance the candidate for this school.
                // lockForUpdate() on count() only locks the aggregate result row,
                // NOT the underlying fee_payments rows — it does not prevent concurrent
                // inserts. Locking the School row serializes ALL receipt generation
                // for this school across concurrent requests.
                $candidate = \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $startNo) {
                    // Acquire an exclusive row lock on the school record.
                    // Any concurrent transaction trying the same will block here
                    // until this transaction commits, guaranteeing sequential counts.
                    \App\Models\School::where('id', $payment->school_id)
                        ->lockForUpdate()
                        ->first();

                    $maxNo = self::withTrashed()
                        ->where('school_id', $payment->school_id)
                        ->count();

                    return $startNo + $maxNo;
                });

                // Safety loop: skip any number whose receipt_no is already in use
                do {
                    $receipt = $prefix . str_pad($candidate, $padLength, '0', STR_PAD_LEFT) . $suffix;
                    $exists  = self::withTrashed()
                        ->where('school_id', $payment->school_id)
                        ->where('receipt_no', $receipt)
                        ->exists();
                    if ($exists) $candidate++;
                } while ($exists);

                $payment->receipt_no = $receipt;

            }
            // Auto-compute balance: Due - Discount + Fine - Paid
            $payment->balance = max(0, $payment->amount_due - $payment->discount + $payment->fine - $payment->amount_paid);
        });
    }

    /**
     * Replace dynamic tokens in a prefix/suffix string.
     * Supported tokens:
     *   {YEAR}   → 4-digit year  e.g. 2026
     *   {YY}     → 2-digit year  e.g. 26
     *   {MONTH}  → 2-digit month e.g. 03
     *   {MM}     → same as {MONTH}
     *   {MON}    → 3-letter month e.g. MAR
     *   {DD}     → 2-digit day
     *   {AY}     → Academic year short e.g. 25-26 (derived from school's active year)
     */
    protected static function resolveTokens(string $template, ?\App\Models\School $school): string
    {
        $now = \Carbon\Carbon::now();

        // Academic year short label (e.g. 25-26)
        $ayShort = '??-??';
        if ($school) {
            $activeYear = \App\Models\AcademicYear::where('school_id', $school->id)
                ->where('is_current', true)
                ->first();
            if ($activeYear) {
                // e.g. "2025-26" → "25-26"
                $parts    = explode('-', $activeYear->name);
                $ayShort  = count($parts) === 2 ? $parts[0] . '-' . $parts[1] : $activeYear->name;
            }
        }

        return str_replace(
            ['{YEAR}', '{YY}', '{MONTH}', '{MM}', '{MON}', '{DD}', '{AY}'],
            [
                $now->format('Y'),   // 2026
                $now->format('y'),   // 26
                $now->format('m'),   // 03
                $now->format('m'),   // 03
                strtoupper($now->format('M')), // MAR
                $now->format('d'),   // 05
                $ayShort,            // 25-26
            ],
            $template
        );
    }
}

