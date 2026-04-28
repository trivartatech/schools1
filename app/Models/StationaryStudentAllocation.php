<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StationaryStudentAllocation extends Model
{
    use HasFactory;

    protected $table = 'stationary_student_allocation';

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'total_amount',
        'amount_paid',
        'discount',
        'fine',
        'balance',
        'payment_status',
        'collection_status',
        'last_payment_date',
        'last_issued_date',
        'status',
        'remarks',
    ];

    protected $casts = [
        'total_amount'      => 'decimal:2',
        'amount_paid'       => 'decimal:2',
        'discount'          => 'decimal:2',
        'fine'              => 'decimal:2',
        'balance'           => 'decimal:2',
        'last_payment_date' => 'date',
        'last_issued_date'  => 'date',
    ];

    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function school()        { return $this->belongsTo(School::class); }
    public function student()       { return $this->belongsTo(Student::class); }
    public function academicYear()  { return $this->belongsTo(AcademicYear::class); }
    public function lineItems()     { return $this->hasMany(StationaryAllocationItem::class, 'allocation_id'); }
    public function payments()      { return $this->hasMany(StationaryFeePayment::class, 'allocation_id'); }
    public function issuances()     { return $this->hasMany(StationaryIssuance::class, 'allocation_id'); }
    public function returns()       { return $this->hasMany(StationaryReturn::class, 'allocation_id'); }

    /**
     * Recompute total_amount from current line items, then derive balance,
     * payment_status from the sum of child receipts. Call after every
     * insert/update/delete of a payment, or when allocation lines change.
     */
    public function recalculateTotals(): void
    {
        $totalAmount = (float) $this->lineItems()
            ->selectRaw('COALESCE(SUM(line_total),0) AS total')
            ->value('total');

        $totals = $this->payments()
            ->selectRaw('COALESCE(SUM(amount_paid),0) AS paid, COALESCE(SUM(discount),0) AS disc, COALESCE(SUM(fine),0) AS fine, MAX(payment_date) AS last_date')
            ->first();

        $paid     = (float) ($totals->paid ?? 0);
        $discount = (float) ($totals->disc ?? 0);
        $fine     = (float) ($totals->fine ?? 0);

        $balance = max(0, $totalAmount - $discount + $fine - $paid);

        $status = 'unpaid';
        if ($this->status === 'inactive' && $paid <= 0) {
            $status = 'waived';
        } elseif ($balance <= 0 && ($paid + $discount) > 0) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        }

        $this->update([
            'total_amount'      => $totalAmount,
            'amount_paid'       => $paid,
            'discount'          => $discount,
            'fine'              => $fine,
            'balance'           => $balance,
            'payment_status'    => $status,
            'last_payment_date' => $totals->last_date,
        ]);
    }

    /**
     * Recompute collection_status from the running qty_collected on each line
     * vs qty_entitled. Called after every issuance/return and on void.
     */
    public function recalculateCollectionStatus(): void
    {
        $sums = $this->lineItems()
            ->selectRaw('COALESCE(SUM(qty_entitled),0) AS entitled, COALESCE(SUM(qty_collected),0) AS collected')
            ->first();

        $entitled  = (int) ($sums->entitled  ?? 0);
        $collected = (int) ($sums->collected ?? 0);

        $status = 'none';
        if ($entitled > 0 && $collected >= $entitled) {
            $status = 'complete';
        } elseif ($collected > 0) {
            $status = 'partial';
        }

        $this->update(['collection_status' => $status]);
    }
}
