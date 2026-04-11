<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'expense_category_id',
        'amount',
        'expense_date',
        'payment_mode',
        'transaction_ref',
        'title',
        'description',
        'attachment_path',
        'recorded_by',
        'gl_transaction_id',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function glTransaction()
    {
        return $this->belongsTo(Transaction::class, 'gl_transaction_id');
    }
}
