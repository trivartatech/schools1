<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HostelStudent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'student_id', 'hostel_bed_id', 'admission_date',
        'vacate_date', 'guardian_name', 'guardian_phone', 'guardian_relation',
        'id_proof', 'medical_info', 'mess_type', 'status', 'fee_payment_id',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function bed()
    {
        return $this->belongsTo(HostelBed::class, 'hostel_bed_id');
    }

    public function feePayment()
    {
        return $this->belongsTo(FeePayment::class);
    }
}
