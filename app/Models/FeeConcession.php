<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeeConcession extends Model
{
    use HasFactory;

    public const FEE_TYPES = ['tuition', 'transport', 'hostel', 'stationary'];

    protected $fillable = [
        'school_id', 'academic_year_id', 'student_id',
        'fee_type',
        'name', 'description', 'type', 'value', 'is_one_time',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'is_one_time'          => 'boolean',
        'value'                => 'decimal:2',
    ];

    protected $attributes = [
        'fee_type' => 'tuition',
    ];

    public function student()      { return $this->belongsTo(Student::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function createdBy()    { return $this->belongsTo(User::class, 'created_by'); }
    public function payments()     { return $this->hasMany(FeePayment::class, 'concession_id'); }
    public function transportPayments()  { return $this->hasMany(TransportFeePayment::class,  'concession_id'); }
    public function hostelPayments()     { return $this->hasMany(HostelFeePayment::class,     'concession_id'); }
    public function stationaryPayments() { return $this->hasMany(StationaryFeePayment::class, 'concession_id'); }

    /**
     * True if this concession has been applied on any payment across any
     * of the four fee streams. Concessions are single-use by design.
     */
    public function isUsed(): bool
    {
        return $this->payments()->exists()
            || $this->transportPayments()->exists()
            || $this->hostelPayments()->exists()
            || $this->stationaryPayments()->exists();
    }

    /**
     * Calculate the discount amount for a given fee amount.
     */
    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'percentage') {
            return round($amount * $this->value / 100, 2);
        }
        return min((float)$this->value, $amount); // fixed, cannot exceed the amount
    }
}
