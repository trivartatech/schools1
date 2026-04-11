<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'school_id',
        'academic_year_id',
        'expense_category_id',
        'name',
        'amount',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function school()          { return $this->belongsTo(School::class); }
    public function academicYear()    { return $this->belongsTo(AcademicYear::class); }
    public function expenseCategory() { return $this->belongsTo(ExpenseCategory::class); }
}
