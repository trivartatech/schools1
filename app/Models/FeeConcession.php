<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeeConcession extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id', 'academic_year_id', 'student_id',
        'name', 'description', 'type', 'value', 'is_one_time',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'is_one_time'          => 'boolean',
        'value'                => 'decimal:2',
    ];

    public function student()      { return $this->belongsTo(Student::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function createdBy()    { return $this->belongsTo(User::class, 'created_by'); }
    public function payments()     { return $this->hasMany(FeePayment::class, 'concession_id'); }

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
