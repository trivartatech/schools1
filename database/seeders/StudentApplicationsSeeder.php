<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\AcademicYear;
use App\Models\CourseClass;

class StudentApplicationsSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = 1;
        $now = Carbon::now();

        $academicYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();
        if (!$academicYear) {
            $this->command->error('No active academic year found. Please run SchoolDummyDataSeeder first.');
            return;
        }

        $classes = CourseClass::where('school_id', $schoolId)->with('sections')->get();
        if ($classes->isEmpty()) {
            $this->command->error('No classes found. Please run SchoolDummyDataSeeder first.');
            return;
        }

        $firstNames  = ['Aditya','Bhavna','Chirag','Deepika','Eshaan','Fatima','Gaurav','Harini',
                        'Ishaan','Jiya','Kabir','Lakshmi','Manav','Nisha','Omkar','Priya',
                        'Rahul','Sana','Tarun','Usha','Vikram','Wania','Yash','Zara'];
        $lastNames   = ['Sharma','Patel','Singh','Kumar','Reddy','Nair','Joshi','Mehta',
                        'Gupta','Verma','Agarwal','Bose','Chowdhury','Das','Iyer'];
        $bloodGroups = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];
        $occupations = ['Doctor','Engineer','Teacher','Businessman','Lawyer','Farmer','Accountant','Nurse'];
        $religions   = ['Hindu','Muslim','Christian','Sikh','Buddhist'];
        $genders     = ['Male','Female','Male','Male','Female'];
        $rejectionReasons = [
            'Incomplete documents submitted. Please reapply with all required certificates.',
            'Class is currently full. Please apply for the next academic year.',
            'Age criteria not met. Student does not meet the minimum age requirement for the selected class.',
        ];
        $localities = ['MG Road','Gandhi Nagar','Nehru Colony','Shivaji Park','Civil Lines'];

        // Clear old data
        DB::table('student_applications')->truncate();

        // Helper to build a base row (all columns always present, nulls for optional)
        $makeRow = function(string $status, int $i) use (
            $firstNames, $lastNames, $bloodGroups, $occupations, $religions,
            $genders, $localities, $rejectionReasons, $academicYear, $classes, $schoolId, $now
        ) {
            $fn      = $firstNames[array_rand($firstNames)];
            $ln      = $lastNames[array_rand($lastNames)];
            $gender  = $genders[array_rand($genders)];
            $class   = $classes->random();
            $section = $class->sections->first();

            $submittedAt = $now->copy()->subDays(rand(1, 14));
            $reviewedAt  = ($status !== 'pending') ? $submittedAt->copy()->addDays(rand(1, 5)) : null;

            return [
                'school_id'         => $schoolId,
                'academic_year_id'  => $academicYear->id,
                'class_id'          => $class->id,
                'section_id'        => $section?->id ?? null,
                'first_name'        => $fn,
                'last_name'         => $ln,
                'dob'               => Carbon::now()->subYears(rand(5, 16))->subDays(rand(0, 365))->format('Y-m-d'),
                'birth_place'       => null,
                'mother_tongue'     => null,
                'gender'            => $gender,
                'blood_group'       => $bloodGroups[array_rand($bloodGroups)],
                'religion'          => $religions[array_rand($religions)],
                'caste'             => null,
                'category'          => null,
                'aadhaar_no'        => null,
                'student_address'   => rand(1, 99) . ', ' . $localities[array_rand($localities)],
                'photo'             => null,
                'primary_phone'     => '9' . rand(100000000, 999999999),
                'father_name'       => 'Mr. ' . $lastNames[array_rand($lastNames)],
                'mother_name'       => 'Mrs. ' . $lastNames[array_rand($lastNames)],
                'guardian_name'     => null,
                'father_phone'      => '9' . rand(100000000, 999999999),
                'mother_phone'      => null,
                'father_occupation' => $occupations[array_rand($occupations)],
                'mother_occupation' => $occupations[array_rand($occupations)],
                'parent_address'    => rand(1, 99) . ', Sector ' . rand(1, 30) . ', Delhi',
                'status'            => $status,
                'rejection_reason'  => ($status === 'rejected') ? $rejectionReasons[$i % count($rejectionReasons)] : null,
                'submitted_at'      => $submittedAt,
                'reviewed_at'       => $reviewedAt,
                'reviewed_by'       => ($status !== 'pending') ? 1 : null,
                'created_at'        => $now,
                'updated_at'        => $now,
                'deleted_at'        => null,
            ];
        };

        $rows = [];

        // 10 Pending
        for ($i = 0; $i < 10; $i++) {
            $rows[] = $makeRow('pending', $i);
        }
        // 5 Approved
        for ($i = 0; $i < 5; $i++) {
            $rows[] = $makeRow('approved', $i);
        }
        // 3 Rejected
        for ($i = 0; $i < 3; $i++) {
            $rows[] = $makeRow('rejected', $i);
        }

        DB::table('student_applications')->insert($rows);

        $this->command->info('✅ Student Applications seeded: 10 pending, 5 approved, 3 rejected.');
    }
}
