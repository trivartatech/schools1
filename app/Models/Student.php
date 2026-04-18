<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes, HasActivityLog;

    protected $fillable = [
        'school_id', 'user_id', 'parent_id', 'admission_no', 'erp_no', 'roll_no',
        'first_name', 'last_name', 'dob', 'birth_place', 'mother_tongue', 'gender', 'blood_group',
        'religion', 'caste', 'category', 'aadhaar_no', 'nationality', 'address',
        'city', 'state', 'pincode', 'emergency_contact_name', 'emergency_contact_phone',
        'admission_date', 'status', 'photo', 'uuid'
    ];

    protected static function booted()
    {
        static::saving(function ($student) {
            if (empty($student->uuid)) {
                $student->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });

        static::creating(function ($student) {
            // Auto-generate ERP number if not already set
            if (empty($student->erp_no) && $student->school_id) {
                $student->erp_no = static::generateErpNo($student->school_id);
            }
        });
    }

    /**
     * Generate a unique, immutable ERP number prefixed with the academic year
     * in which the student is first registered. Format: 2025-26/0001
     */
    public static function generateErpNo(int $schoolId): string
    {
        // Get current academic year name for prefix
        $ayName = '0000-00';
        if (app()->bound('current_academic_year_id')) {
            $ay = AcademicYear::find(app('current_academic_year_id'));
            if ($ay) {
                $ayName = $ay->name; // e.g. "2025-26"
            }
        }

        $prefix = $ayName . '/';

        // Find the max existing ERP number with this prefix for this school
        $lastErp = static::withTrashed()
            ->where('school_id', $schoolId)
            ->where('erp_no', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(erp_no, ?) AS UNSIGNED) DESC', [strlen($prefix) + 1])
            ->value('erp_no');

        if ($lastErp) {
            $lastSeq = (int) substr($lastErp, strlen($prefix));
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function studentParent()
    {
        return $this->belongsTo(StudentParent::class, 'parent_id');
    }

    public function academicHistories()
    {
        return $this->hasMany(StudentAcademicHistory::class);
    }

    public function currentAcademicHistory()
    {
        if (app()->bound('current_academic_year_id')) {
            return $this->hasOne(StudentAcademicHistory::class)
                ->where('academic_year_id', app('current_academic_year_id'));
        }

        // fallback if called outside of the request lifecycle
        return $this->hasOne(StudentAcademicHistory::class)->latestOfMany();
    }

    public function documents()
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function healthRecord()
    {
        return $this->hasOne(StudentHealthRecord::class);
    }

    public function editRequests()
    {
        return $this->morphMany(EditRequest::class, 'requestable');
    }

    public function feePayments()
    {
        return $this->hasMany(\App\Models\FeePayment::class);
    }

    public function transportAllocation()
    {
        return $this->hasOne(\App\Models\TransportStudentAllocation::class)
            ->where('status', 'active');
    }

    public function hostelAllocation()
    {
        return $this->hasOne(\App\Models\HostelStudent::class)
            ->whereNull('vacate_date')
            ->whereRaw('LOWER(status) = ?', ['active']); // case-insensitive: handles 'Active' and 'active'
    }

    public function alumni()
    {
        return $this->hasOne(\App\Models\Alumni::class);
    }

    public function transferCertificates()
    {
        return $this->hasMany(TransferCertificate::class);
    }

    public function latestTransferCertificate()
    {
        return $this->hasOne(TransferCertificate::class)->latestOfMany();
    }

    protected $casts = [
        'dob'            => 'date',
        'admission_date' => 'date',
        'status'         => StudentStatus::class,
    ];

    protected $appends = ['photo_url', 'name'];

    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return null;
    }

    public function getNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
