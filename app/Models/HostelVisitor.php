<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HostelVisitor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'student_id', 'visitor_name', 'relation', 'phone',
        'date', 'in_time', 'out_time', 'purpose', 'id_proof', 'id_proof_type',
        'otp', 'is_approved', 'remarks', 'visitor_type', 'meet_user_type',
        'staff_id', 'visitor_count', 'visitor_photo', 'pass_token',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->pass_token = $model->pass_token ?? Str::random(32);
        });
    }

    public function school() { return $this->belongsTo(School::class); }
    public function student() { return $this->belongsTo(Student::class); }
    public function staff() { return $this->belongsTo(\App\Models\Staff::class); }
}
