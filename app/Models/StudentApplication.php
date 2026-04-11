<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'class_id', 'section_id',
        'transport_route_id', 'transport_stop_id', 'transport_pickup_type',
        'reg_no',
        'first_name', 'last_name', 'dob', 'birth_place', 'mother_tongue',
        'gender', 'blood_group', 'religion', 'caste', 'category', 'aadhaar_no', 'nationality',
        'student_address', 'city', 'state', 'pincode', 'photo',
        'emergency_contact_name', 'emergency_contact_phone',
        'primary_phone', 'father_name', 'mother_name', 'guardian_name',
        'guardian_email', 'guardian_phone',
        'father_phone', 'mother_phone',
        'father_occupation', 'father_qualification',
        'mother_occupation', 'mother_qualification',
        'parent_address',
        'previous_school', 'previous_class', 'annual_income',
        'status', 'rejection_reason', 'submitted_at', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'dob'          => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    // Auto-generate Registration Number on creation
    protected static function booted(): void
    {
        static::creating(function (self $app) {
            if (empty($app->reg_no)) {
                $school   = \App\Models\School::find($app->school_id);
                $settings = $school?->settings ?? [];

                $prefix    = $settings['reg_prefix']    ?? 'REG-';
                $suffix    = $settings['reg_suffix']    ?? '';
                $startNo   = (int) ($settings['reg_start_no']   ?? 1);
                $padLength = (int) ($settings['reg_pad_length']  ?? 4);

                $prefix = self::resolveTokens($prefix, $school);
                $suffix = self::resolveTokens($suffix, $school);

                $count  = self::where('school_id', $app->school_id)->count();
                $nextNo = $startNo + $count;

                $app->reg_no = $prefix . str_pad($nextNo, $padLength, '0', STR_PAD_LEFT) . $suffix;
            }
        });
    }

    /**
     * Resolve date/year tokens (mirrors FeePayment::resolveTokens).
     * {YEAR} {YY} {MONTH} {MM} {MON} {DD} {AY}
     */
    protected static function resolveTokens(string $template, ?\App\Models\School $school): string
    {
        $now = \Carbon\Carbon::now();
        $ayShort = '??-??';
        if ($school) {
            $ay = \App\Models\AcademicYear::where('school_id', $school->id)->where('is_current', true)->first();
            if ($ay) {
                $parts   = explode('-', $ay->name);
                $ayShort = count($parts) === 2 ? $parts[0] . '-' . $parts[1] : $ay->name;
            }
        }
        return str_replace(
            ['{YEAR}', '{YY}', '{MONTH}', '{MM}', '{MON}', '{DD}', '{AY}'],
            [$now->format('Y'), $now->format('y'), $now->format('m'), $now->format('m'),
             strtoupper($now->format('M')), $now->format('d'), $ayShort],
            $template
        );
    }

    // Status helpers
    public function isPending():  bool { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function school()       { return $this->belongsTo(School::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function courseClass()  { return $this->belongsTo(CourseClass::class, 'class_id'); }
    public function section()      { return $this->belongsTo(Section::class); }
    public function reviewer()     { return $this->belongsTo(User::class, 'reviewed_by'); }
}
