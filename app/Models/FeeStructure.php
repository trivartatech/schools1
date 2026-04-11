<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeeStructure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'class_id', 'fee_head_id',
        'term', 'amount', 'late_fee_per_day', 'due_date',
        'is_optional', 'student_type', 'gender',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'late_fee_per_day' => 'decimal:2',
        'due_date'         => 'date',
        'is_optional'      => 'boolean',
    ];

    public function feeHead()   { return $this->belongsTo(FeeHead::class); }
    public function courseClass() { return $this->belongsTo(CourseClass::class, 'class_id'); }
    public function academicYear(){ return $this->belongsTo(AcademicYear::class); }
}
