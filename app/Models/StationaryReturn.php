<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StationaryReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stationary_returns';

    protected $fillable = [
        'school_id', 'allocation_id', 'student_id',
        'accepted_by', 'returned_at',
        'refund_amount', 'refund_mode',
        'gl_transaction_id', 'remarks',
    ];

    protected $casts = [
        'returned_at'   => 'datetime',
        'refund_amount' => 'decimal:2',
    ];

    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function school()        { return $this->belongsTo(School::class); }
    public function allocation()    { return $this->belongsTo(StationaryStudentAllocation::class, 'allocation_id'); }
    public function student()       { return $this->belongsTo(Student::class); }
    public function acceptedBy()    { return $this->belongsTo(User::class, 'accepted_by'); }
    public function items()         { return $this->hasMany(StationaryReturnItem::class, 'return_id'); }
    public function glTransaction() { return $this->belongsTo(Transaction::class, 'gl_transaction_id'); }
}
