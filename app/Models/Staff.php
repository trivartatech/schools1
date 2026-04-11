<?php

namespace App\Models;

use App\Enums\StaffStatus;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory, SoftDeletes, HasActivityLog;

    protected $fillable = [
        'school_id',
        'user_id',
        'department_id',
        'designation_id',
        'employee_id',
        'qualification',
        'experience_years',
        'joining_date',
        'basic_salary',
        'bank_name',
        'bank_account_no',
        'ifsc_code',
        'pan_no',
        'epf_no',
        'status',
        'photo',
        'signature',
        'allowances_config',
        'deductions_config',
        'tax_config',
    ];

    protected $casts = [
        'joining_date'      => 'date',
        'experience_years'  => 'integer',
        'basic_salary'      => 'decimal:2',
        'allowances_config' => 'array',
        'deductions_config' => 'array',
        'tax_config'        => 'array',
        'status'            => StaffStatus::class,
    ];

    /**
     * Scope a query to only include records for the current school.
     */
    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class, 'user_id', 'user_id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    protected $appends = ['photo_url', 'signature_url'];

    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return null;
    }

    public function getSignatureUrlAttribute()
    {
        if ($this->signature) {
            return asset('storage/' . $this->signature);
        }
        return null;
    }

    public function editRequests()
    {
        return $this->morphMany(EditRequest::class, 'requestable');
    }
}
