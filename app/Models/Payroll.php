<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'staff_id',
        'month',
        'year',
        'basic_pay',
        'allowances',
        'deductions',
        'unpaid_leave_days',
        'unpaid_leave_deduction',
        'net_salary',
        'status',
        'payment_date',
        'payment_mode',
        'gl_transaction_id',
    ];

    protected $casts = [
        'allowances' => 'array',
        'deductions' => 'array',
        'basic_pay' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'payment_date' => 'date:Y-m-d',
    ];

    /**
     * Scope a query to only include records for the current school.
     */
    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function glTransaction()
    {
        return $this->belongsTo(Transaction::class, 'gl_transaction_id');
    }
}
