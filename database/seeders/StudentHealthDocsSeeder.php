<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class StudentHealthDocsSeeder extends Seeder
{
    public function run(): void
    {
        $school         = DB::table('schools')->first();
        $schoolId       = $school->id;
        $now            = Carbon::now();
        $academicYearId = DB::table('academic_years')->where('school_id', $schoolId)->where('status', 'active')->value('id');
        $adminUserId    = DB::table('users')->where('school_id', $schoolId)->whereIn('user_type', ['principal', 'admin'])->value('id');

        Schema::disableForeignKeyConstraints();
        DB::table('student_health_records')->where('school_id', $schoolId)->delete();
        DB::table('student_documents')->where('school_id', $schoolId)->delete();
        DB::table('student_leaves')->where('school_id', $schoolId)->delete();
        DB::table('transfer_certificates')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        $students = DB::table('students')->where('school_id', $schoolId)->get();

        $bloodGroups  = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
        $allergies    = [null, 'Dust allergy', 'Pollen allergy', 'Nut allergy', 'Lactose intolerant', null, null];
        $conditions   = [null, null, 'Mild asthma', null, 'Diabetes Type 1', null, null, null];
        $disabilities = [null, null, null, 'Mild hearing impairment', null, null];
        $vaccinations = [
            json_encode(['BCG' => true, 'Polio' => true, 'Hepatitis B' => true, 'MMR' => true]),
            json_encode(['BCG' => true, 'Polio' => true, 'Hepatitis B' => false, 'MMR' => true]),
            json_encode(['BCG' => true, 'Polio' => true, 'Hepatitis B' => true, 'MMR' => false]),
        ];

        $healthCount = 0;
        $docCount    = 0;
        $leaveCount  = 0;
        $tcCount     = 0;

        // ── 1. Health Records (all students) ──────────────────────────────────
        foreach ($students as $idx => $student) {
            DB::table('student_health_records')->insert([
                'school_id'                => $schoolId,
                'student_id'               => $student->id,
                'height_cm'                => rand(120, 175) + 0.5,
                'weight_kg'                => rand(25, 70) + 0.3,
                'vision_left'              => ['6/6', '6/9', '6/12'][$idx % 3],
                'vision_right'             => ['6/6', '6/9', '6/12'][$idx % 3],
                'hearing'                  => 'Normal',
                'known_allergies'          => $allergies[$idx % count($allergies)],
                'chronic_conditions'       => $conditions[$idx % count($conditions)],
                'current_medications'      => ($idx % 8 === 4) ? 'Insulin (daily)' : null,
                'disability'               => $disabilities[$idx % count($disabilities)],
                'vaccinations'             => $vaccinations[$idx % count($vaccinations)],
                'emergency_contact_name'   => 'Parent of Student ' . $student->id,
                'emergency_contact_phone'  => '98' . rand(10000000, 99999999),
                'emergency_contact_relation' => ['Father', 'Mother'][$idx % 2],
                'family_doctor_name'       => 'Dr. ' . ['Sharma', 'Gupta', 'Verma', 'Singh'][$idx % 4],
                'family_doctor_phone'      => '011-' . rand(20000000, 29999999),
                'created_at'               => $now, 'updated_at' => $now,
            ]);
            $healthCount++;
        }

        // ── 2. Student Documents (sample for ~30% of students) ────────────────
        $docTypes = ['Aadhar Card', 'Birth Certificate', 'Transfer Certificate', 'Previous Marksheet', 'Caste Certificate', 'Passport Photo'];
        foreach ($students->take((int)(count($students) * 0.3)) as $idx => $student) {
            $docType = $docTypes[$idx % count($docTypes)];
            DB::table('student_documents')->insert([
                'school_id'             => $schoolId,
                'student_id'            => $student->id,
                'document_type'         => $docType,
                'title'                 => $docType . ' — ' . $student->first_name . ' ' . $student->last_name,
                'is_original_submitted' => ($idx % 2 === 0),
                'original_file_location'=> 'File Cabinet 3, Folder ' . ($idx + 1),
                'file_path'             => 'documents/' . $schoolId . '/student_' . $student->id . '_' . Str_slug($docType) . '.pdf',
                'uploaded_by'           => $adminUserId,
                'created_at'            => $now, 'updated_at' => $now,
            ]);
            $docCount++;
        }

        // ── 3. Student Leaves ──────────────────────────────────────────────────
        $leaveTypeId = DB::table('leave_types')->where('school_id', $schoolId)->where('code', 'SL')->value('id')
                    ?? DB::table('leave_types')->where('school_id', $schoolId)->value('id');

        $leaveReasons  = [
            'Fever and cold — doctor prescribed rest',
            'Family function out of station',
            'Medical appointment at hospital',
            'Bereavement in family',
            'Personal emergency',
        ];
        $leaveStatuses = ['approved', 'approved', 'pending', 'rejected'];

        foreach ($students->take(40) as $idx => $student) {
            $startDate = Carbon::now()->subDays(rand(5, 60));
            $duration  = rand(1, 3);
            $endDate   = $startDate->copy()->addDays($duration - 1);
            $status    = $leaveStatuses[$idx % count($leaveStatuses)];

            DB::table('student_leaves')->insert([
                'school_id'     => $schoolId,
                'student_id'    => $student->id,
                'leave_type_id' => $leaveTypeId,
                'start_date'    => $startDate->format('Y-m-d'),
                'end_date'      => $endDate->format('Y-m-d'),
                'reason'        => $leaveReasons[$idx % count($leaveReasons)],
                'status'        => $status,
                'approved_by'   => in_array($status, ['approved', 'rejected']) ? $adminUserId : null,
                'applied_by'    => $adminUserId,
                'remarks'       => $status === 'rejected' ? 'Insufficient reason provided.' : null,
                'created_at'    => $startDate->subDay(), 'updated_at' => $startDate,
            ]);
            $leaveCount++;
        }

        // ── 4. Transfer Certificates (a few issued, a few pending) ─────────────
        $tcReasons   = ['Moving to another city', 'Admission in another school', 'Parent job transfer', 'Board exam complete'];
        $tcStatuses  = ['requested', 'approved', 'issued', 'rejected'];
        $conducts    = ['Excellent', 'Good', 'Good', 'Satisfactory'];

        foreach ($students->take(6) as $idx => $student) {
            $tcStatus   = $tcStatuses[$idx % count($tcStatuses)];
            $leavingDate= Carbon::now()->subDays(rand(5, 30));

            DB::table('transfer_certificates')->insert([
                'school_id'          => $schoolId,
                'student_id'         => $student->id,
                'certificate_no'     => 'TC/' . date('Y') . '/' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT),
                'status'             => $tcStatus,
                'leaving_date'       => $leavingDate->format('Y-m-d'),
                'reason'             => $tcReasons[$idx % count($tcReasons)],
                'conduct'            => $conducts[$idx % count($conducts)],
                'last_class_studied' => 'Class ' . rand(8, 10),
                'fee_paid_upto'      => Carbon::now()->format('Y-m-d'),
                'has_dues'           => false,
                'remarks'            => in_array($tcStatus, ['issued']) ? 'TC issued after clearance.' : null,
                'requested_by'       => $adminUserId,
                'approved_by'        => in_array($tcStatus, ['approved', 'issued']) ? $adminUserId : null,
                'approved_at'        => in_array($tcStatus, ['approved', 'issued']) ? $leavingDate->addDay() : null,
                'issued_at'          => $tcStatus === 'issued' ? $leavingDate->addDays(3) : null,
                'created_at'         => $leavingDate, 'updated_at' => $leavingDate,
            ]);
            $tcCount++;
        }

        $this->command->info('✅ Student Health, Docs & Leaves seeded!');
        $this->command->info("   - {$healthCount} Health Records");
        $this->command->info("   - {$docCount} Student Documents");
        $this->command->info("   - {$leaveCount} Student Leaves");
        $this->command->info("   - {$tcCount} Transfer Certificates");
    }
}

function Str_slug(string $str): string
{
    return strtolower(str_replace([' ', '/', '\\'], '_', $str));
}
