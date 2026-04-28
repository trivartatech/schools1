<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentParent;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdmissionService
{
    /**
     * Process a new student admission.
     */
    public function admitStudent(array $data, int $schoolId, int $academicYearId)
    {
        return DB::transaction(function () use ($data, $schoolId, $academicYearId) {
            // 1. Handle Parent - find existing by primary phone or create new
            $parent = StudentParent::where('school_id', $schoolId)
                ->where('primary_phone', $data['primary_phone'])
                ->first();

            if (!$parent) {
                // Create User for Parent
                // Default password is 'password' across the whole app —
                // matches portal:create-users and the User Management
                // reset / create-missing flows.
                $parentUser = User::create([
                    'school_id' => $schoolId,
                    'name'      => $data['father_name'] ?: ($data['guardian_name'] ?: 'Parent'),
                    'username'  => $data['primary_phone'],
                    'phone'     => $data['primary_phone'],
                    'password'  => \Illuminate\Support\Facades\Hash::make('password'),
                    'user_type' => 'parent',
                    'is_active' => true,
                ]);
                $parentUser->assignRole('parent');

                $parent = StudentParent::create([
                    'school_id'            => $schoolId,
                    'user_id'              => $parentUser->id,
                    'primary_phone'        => $data['primary_phone'],
                    'father_name'          => $data['father_name']          ?? null,
                    'mother_name'          => $data['mother_name']          ?? null,
                    'guardian_name'        => $data['guardian_name']        ?? null,
                    'guardian_email'       => $data['guardian_email']       ?? null,
                    'guardian_phone'       => $data['guardian_phone']       ?? null,
                    'father_phone'         => $data['father_phone']         ?? null,
                    'mother_phone'         => $data['mother_phone']         ?? null,
                    'father_occupation'    => $data['father_occupation']    ?? null,
                    'father_qualification' => $data['father_qualification'] ?? null,
                    'mother_occupation'    => $data['mother_occupation']    ?? null,
                    'mother_qualification' => $data['mother_qualification'] ?? null,
                    'address'              => $data['parent_address']       ?? null,
                ]);
            }

            // 2. Generate Admission Number using school's configurable format
            // Lock the school row to serialize concurrent admission requests for the same school
            $school   = \App\Models\School::where('id', $schoolId)->lockForUpdate()->first();
            $settings = $school?->settings ?? [];

            $admPrefix    = $settings['adm_prefix']    ?? 'ADM';
            $admSuffix    = $settings['adm_suffix']    ?? '';
            $admStartNo   = (int) ($settings['adm_start_no']   ?? 1);
            $admPadLength = (int) ($settings['adm_pad_length']  ?? 4);

            // Resolve date/year tokens  (shared helper)
            $admPrefix = static::resolveTokens($admPrefix, $school);
            $admSuffix = static::resolveTokens($admSuffix, $school);

            // Use withTrashed() so soft-deleted records are counted — prevents gap re-use.
            // Then verify uniqueness and increment until a free number is found (race-safe).
            $admissionNo = null;
            $baseCount = Student::withTrashed()->where('school_id', $schoolId)->count() + $admStartNo;
            for ($attempt = 0; $attempt < 50; $attempt++) {
                $candidate = $admPrefix . str_pad($baseCount + $attempt, $admPadLength, '0', STR_PAD_LEFT) . $admSuffix;
                $exists = Student::withTrashed()
                    ->where('school_id', $schoolId)
                    ->where('admission_no', $candidate)
                    ->exists();
                if (!$exists) {
                    $admissionNo = $candidate;
                    break;
                }
            }
            if (!$admissionNo) {
                throw ValidationException::withMessages([
                    'admission_no' => 'Could not generate a unique admission number. Please contact support.',
                ]);
            }


            // 3. Auto-generate Roll Number (max existing roll in same class+section+year + 1)
            // Using MAX is race-safer than COUNT because concurrent inserts won't both get the same value.
            if (!empty($data['roll_no'])) {
                $rollNo = $data['roll_no'];
            } else {
                $maxRoll = StudentAcademicHistory::where('school_id', $schoolId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('class_id', $data['class_id'])
                    ->where('section_id', $data['section_id'] ?? null)
                    ->max('roll_no');
                $rollNo = str_pad(((int) $maxRoll) + 1, 2, '0', STR_PAD_LEFT);
            }

            // 3. Handle Photo Upload
            $photoPath = null;
            if (isset($data['photo']) && $data['photo'] instanceof \Illuminate\Http\UploadedFile) {
                $photoPath = $data['photo']->store('students/photos', 'public');
            }

            // 4. Create User for Student — same default password as the parent
            $studentUser = User::create([
                'school_id' => $schoolId,
                'name'      => $data['first_name'] . (isset($data['last_name']) ? ' ' . $data['last_name'] : ''),
                'username'  => $admissionNo,
                'password'  => \Illuminate\Support\Facades\Hash::make('password'),
                'user_type' => 'student',
                'is_active' => true,
            ]);
            $studentUser->assignRole('student');

            // 5. Create Student record
            $student = Student::create([
                'school_id'               => $schoolId,
                'user_id'                 => $studentUser->id,
                'parent_id'               => $parent->id,
                'admission_no'            => $admissionNo,
                'roll_no'                 => $rollNo,
                'first_name'              => $data['first_name'],
                'last_name'               => $data['last_name']               ?? null,
                'dob'                     => $data['dob']                     ?? null,
                'birth_place'             => $data['birth_place']             ?? null,
                'mother_tongue'           => $data['mother_tongue']           ?? null,
                'gender'                  => $data['gender']                  ?? null,
                'blood_group'             => $data['blood_group']             ?? null,
                'religion'                => $data['religion']                ?? null,
                'caste'                   => $data['caste']                   ?? null,
                'category'                => $data['category']                ?? null,
                'aadhaar_no'              => $data['aadhaar_no']              ?? null,
                'nationality'             => $data['nationality']             ?? 'Indian',
                'address'                 => $data['student_address']         ?? $parent->address,
                'city'                    => $data['city']                    ?? null,
                'state'                   => $data['state']                   ?? null,
                'pincode'                 => $data['pincode']                 ?? null,
                'emergency_contact_name'  => $data['emergency_contact_name']  ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'admission_date'          => $data['admission_date']          ?? now()->format('Y-m-d'),
                'status'                  => 'active',
                'photo'                   => $photoPath,
            ]);

            // 6. Create Academic History
            StudentAcademicHistory::create([
                'school_id' => $schoolId,
                'student_id' => $student->id,
                'academic_year_id' => $academicYearId,
                'class_id' => $data['class_id'],
                'section_id' => $data['section_id'] ?? null,
                'status' => 'current',
            ]);

            // 7. Auto-add to section chat group
            if (!empty($data['section_id'])) {
                $section = \App\Models\Section::find($data['section_id']);
                if ($section) {
                    $chatService = app(\App\Services\ChatService::class);
                    // Ensure group exists
                    $conv = $chatService->ensureSectionGroup($section, $schoolId);
                    // Add student user
                    \App\Models\ChatParticipant::firstOrCreate(
                        ['conversation_id' => $conv->id, 'user_id' => $studentUser->id],
                        ['role' => 'member', 'joined_at' => now()]
                    );
                    // Add parent user
                    if ($parent->user_id) {
                        \App\Models\ChatParticipant::firstOrCreate(
                            ['conversation_id' => $conv->id, 'user_id' => $parent->user_id],
                            ['role' => 'member', 'joined_at' => now()]
                        );
                    }
                }
            }

            return $student;
        });
    }

    /**
     * Resolve dynamic date/year tokens in prefix/suffix.
     * Supports: {YEAR} {YY} {MONTH} {MM} {MON} {DD} {AY}
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
            [
                $now->format('Y'),             // {YEAR} → 2026
                $now->format('y'),             // {YY}   → 26
                $now->format('F'),             // {MONTH}→ April  (was wrongly 'm' = '04')
                $now->format('m'),             // {MM}   → 04
                strtoupper($now->format('M')), // {MON}  → APR
                $now->format('d'),             // {DD}   → 02
                $ayShort,                      // {AY}   → 2025-26
            ],
            $template
        );
    }
}
