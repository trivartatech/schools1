<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GatePass extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'pass_type', 
        'user_type', 'user_id', 
        'requested_by_type', 'requested_by_id',
        'verified_by', 'verification_method',
        'picked_up_by_name', 'relationship', 'picker_photo_path',
        'status', 'qr_code_token', 'exit_time', 'return_time',
        'reason', 'approval_notes'
    ];

    protected $casts = [
        'exit_time' => 'datetime',
        'return_time' => 'datetime',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function user()
    {
        return $this->morphTo();
    }

    public function requestedBy()
    {
        return $this->morphTo();
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
