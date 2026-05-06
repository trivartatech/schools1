<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OnlinePaymentOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id', 'student_id', 'academic_year_id', 'initiated_by',
        'gateway', 'gateway_order_id', 'gateway_payment_id', 'gateway_signature',
        'amount_paise', 'currency', 'fee_items',
        'status', 'failure_reason', 'paid_at', 'processed_at',
    ];

    protected $appends = ['amount'];

    protected $casts = [
        'fee_items'    => 'array',
        'amount_paise' => 'integer',
        'paid_at'      => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function school()       { return $this->belongsTo(School::class); }
    public function student()      { return $this->belongsTo(Student::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function initiatedBy()  { return $this->belongsTo(User::class, 'initiated_by'); }

    /** Amount in rupees (for display). */
    public function getAmountAttribute(): float
    {
        return $this->amount_paise / 100;
    }
}
