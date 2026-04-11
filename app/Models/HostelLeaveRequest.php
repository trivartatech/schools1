<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HostelLeaveRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'student_id', 'leave_type', 'from_date', 'to_date', 'reason',
        'destination', 'status', 'approved_by', 'actual_out_time', 'actual_in_time', 'late_reason',
        // Escort
        'escort_name', 'escort_relation', 'escort_phone', 'escort_id_proof_type', 'escort_id_proof_photo',
        // Parent approval
        'parent_approval', 'parent_name', 'parent_otp', 'parent_otp_verified', 'parent_approved_at',
        // Gate photos
        'student_exit_photo', 'escort_exit_photo', 'student_return_photo',
        // QR pass
        'pass_token', 'is_expired',
    ];

    protected $casts = [
        'parent_otp_verified' => 'boolean',
        'is_expired' => 'boolean',
        'parent_approved_at' => 'datetime',
        'actual_out_time' => 'datetime',
        'actual_in_time' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->pass_token = $model->pass_token ?? Str::random(32);
        });
    }

    public function school() { return $this->belongsTo(School::class); }
    public function student() { return $this->belongsTo(Student::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
