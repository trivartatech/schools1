<?php

namespace App\Models;

use App\Enums\PaymentMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single hostel-fee receipt.
 *
 * Receipt numbers are generated from the school's configurable
 * `hostel_receipt_*` settings (prefix/suffix/start_no/pad_length),
 * completely independent of the Finance module's fee_receipt_* settings.
 */
class HostelFeePayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hostel_fee_payments';

    protected $fillable = [
        'receipt_no', 'school_id', 'allocation_id', 'student_id', 'academic_year_id',
        'amount_paid', 'discount', 'concession_id', 'fine',
        'payment_date', 'payment_mode', 'transaction_ref', 'remarks',
        'collected_by', 'gl_transaction_id',
    ];

    protected $casts = [
        'amount_paid'  => 'decimal:2',
        'discount'     => 'decimal:2',
        'fine'         => 'decimal:2',
        'payment_date' => 'date',
        'payment_mode' => PaymentMode::class,
    ];

    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function allocation()    { return $this->belongsTo(HostelStudent::class, 'allocation_id'); }
    public function student()       { return $this->belongsTo(Student::class); }
    public function school()        { return $this->belongsTo(School::class); }
    public function academicYear()  { return $this->belongsTo(AcademicYear::class); }
    public function collectedBy()   { return $this->belongsTo(User::class, 'collected_by'); }
    public function glTransaction() { return $this->belongsTo(Transaction::class, 'gl_transaction_id'); }

    /**
     * Compatibility accessor: NotificationService::notifyFeePayment() reads
     * $payment->amount, but our column is amount_paid. Expose the same name
     * so the unified notification flow works for hostel + tuition + transport.
     */
    public function getAmountAttribute()
    {
        return $this->amount_paid;
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment) {
            if (empty($payment->receipt_no)) {
                $school   = School::find($payment->school_id);
                $settings = $school?->settings ?? [];

                $prefix    = $settings['hostel_receipt_prefix']     ?? 'HS-';
                $suffix    = $settings['hostel_receipt_suffix']     ?? '';
                $startNo   = (int) ($settings['hostel_receipt_start_no']   ?? 1);
                $padLength = (int) ($settings['hostel_receipt_pad_length'] ?? 5);

                $prefix = self::resolveTokens($prefix, $school);
                $suffix = self::resolveTokens($suffix, $school);

                $candidate = \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $startNo) {
                    School::where('id', $payment->school_id)->lockForUpdate()->first();

                    $count = self::withTrashed()
                        ->where('school_id', $payment->school_id)
                        ->count();

                    return $startNo + $count;
                });

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
        });
    }

    /**
     * Same token set as FeePayment::resolveTokens() — kept in sync so
     * that hostel and regular receipts support identical formatting.
     */
    protected static function resolveTokens(string $template, ?School $school): string
    {
        $now = \Carbon\Carbon::now();

        $ayShort = '??-??';
        if ($school) {
            $activeYear = AcademicYear::where('school_id', $school->id)
                ->where('is_current', true)
                ->first();
            if ($activeYear) {
                $parts   = explode('-', $activeYear->name);
                $ayShort = count($parts) === 2 ? $parts[0] . '-' . $parts[1] : $activeYear->name;
            }
        }

        return str_replace(
            ['{YEAR}', '{YY}', '{MONTH}', '{MM}', '{MON}', '{DD}', '{AY}'],
            [
                $now->format('Y'),
                $now->format('y'),
                $now->format('m'),
                $now->format('m'),
                strtoupper($now->format('M')),
                $now->format('d'),
                $ayShort,
            ],
            $template
        );
    }
}
