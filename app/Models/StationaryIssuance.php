<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StationaryIssuance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stationary_issuances';

    protected $fillable = [
        'school_id', 'allocation_id', 'student_id',
        'issued_by', 'issued_at', 'remarks',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function school()      { return $this->belongsTo(School::class); }
    public function allocation()  { return $this->belongsTo(StationaryStudentAllocation::class, 'allocation_id'); }
    public function student()     { return $this->belongsTo(Student::class); }
    public function issuedBy()    { return $this->belongsTo(User::class, 'issued_by'); }
    public function items()       { return $this->hasMany(StationaryIssuanceItem::class, 'issuance_id'); }
}
