<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransportStudentAllocation extends Model
{
    use HasFactory;

    protected $table = 'transport_student_allocation';

    protected $fillable = [
        'school_id',
        'student_id',
        'route_id',
        'stop_id',
        'vehicle_id',
        'transport_fee',
        'months_opted',
        'amount_paid',
        'discount',
        'fine',
        'balance',
        'payment_status',
        'last_payment_date',
        'pickup_type',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'transport_fee'     => 'decimal:2',
        'months_opted'      => 'decimal:2',
        'amount_paid'       => 'decimal:2',
        'discount'          => 'decimal:2',
        'fine'              => 'decimal:2',
        'balance'           => 'decimal:2',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'last_payment_date' => 'date',
    ];

    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function stop()
    {
        return $this->belongsTo(TransportStop::class, 'stop_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(TransportVehicle::class, 'vehicle_id');
    }

    public function payments()
    {
        return $this->hasMany(TransportFeePayment::class, 'allocation_id');
    }

    /**
     * Recompute balance + payment_status from the sum of child receipts and
     * the current transport_fee. Call after every insert/update/delete of
     * a TransportFeePayment, or when transport_fee changes.
     */
    public function recalculateTotals(): void
    {
        $totals = $this->payments()
            ->selectRaw('COALESCE(SUM(amount_paid),0) AS paid, COALESCE(SUM(discount),0) AS disc, COALESCE(SUM(fine),0) AS fine, MAX(payment_date) AS last_date')
            ->first();

        $paid     = (float) ($totals->paid ?? 0);
        $discount = (float) ($totals->disc ?? 0);
        $fine     = (float) ($totals->fine ?? 0);
        $fee      = (float) $this->transport_fee;

        $balance = max(0, $fee - $discount + $fine - $paid);

        $status = 'unpaid';
        if ($this->status === 'inactive' && $paid <= 0) {
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
