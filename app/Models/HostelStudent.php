<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HostelStudent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'student_id', 'hostel_bed_id', 'admission_date',
        'vacate_date', 'guardian_name', 'guardian_phone', 'guardian_relation',
        'id_proof', 'medical_info', 'mess_type', 'status', 'fee_payment_id',
        'hostel_fee', 'months_opted', 'amount_paid', 'discount', 'fine',
        'balance', 'payment_status', 'last_payment_date',
    ];

    protected $casts = [
        'hostel_fee'        => 'decimal:2',
        'months_opted'      => 'decimal:2',
        'amount_paid'       => 'decimal:2',
        'discount'          => 'decimal:2',
        'fine'              => 'decimal:2',
        'balance'           => 'decimal:2',
        'admission_date'    => 'date',
        'vacate_date'       => 'date',
        'last_payment_date' => 'date:Y-m-d',
    ];

    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function bed()
    {
        return $this->belongsTo(HostelBed::class, 'hostel_bed_id');
    }

    public function feePayment()
    {
        return $this->belongsTo(FeePayment::class);
    }

    public function payments()
    {
        return $this->hasMany(HostelFeePayment::class, 'allocation_id');
    }

    /**
     * Recompute balance + payment_status from the sum of child receipts and
     * the current hostel_fee. Call after every insert/update/delete of
     * a HostelFeePayment, or when hostel_fee changes.
     */
    public function recalculateTotals(): void
    {
        $totals = $this->payments()
            ->selectRaw('COALESCE(SUM(amount_paid),0) AS paid, COALESCE(SUM(discount),0) AS disc, COALESCE(SUM(fine),0) AS fine, MAX(payment_date) AS last_date')
            ->first();

        $paid     = (float) ($totals->paid ?? 0);
        $discount = (float) ($totals->disc ?? 0);
        $fine     = (float) ($totals->fine ?? 0);
        $fee      = (float) $this->hostel_fee;

        $balance = max(0, $fee - $discount + $fine - $paid);

        $status = 'unpaid';
        if ($this->status === 'Vacated' && $paid <= 0) {
            $status = 'waived';
        } elseif ($balance <= 0 && ($paid + $discount) > 0) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        }

        $this->update([
            'amount_paid'       => $paid,
            'discount'          => $discount,
            'fine'              => $fine,
            'balance'           => $balance,
            'payment_status'    => $status,
            'last_payment_date' => $totals->last_date,
        ]);
    }
}
