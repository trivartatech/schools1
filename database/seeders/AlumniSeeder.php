<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AlumniSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        $academicYearId = DB::table('academic_years')->where('school_id', $schoolId)
            ->orderByDesc('start_date')->value('id');

        $adminUserId = DB::table('users')->where('school_id', $schoolId)
            ->whereIn('user_type', ['principal', 'admin', 'school_admin'])
            ->value('id');

        Schema::disableForeignKeyConstraints();
        DB::table('alumni')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // Pick the first 20 students from Class 10/11/12 sections to mark as graduated
        $classIds = DB::table('course_classes')->where('school_id', $schoolId)
            ->whereIn('numeric_value', [10, 11, 12])->pluck('id')->toArray();

        if (empty($classIds)) {
            $this->command->info('AlumniSeeder: no senior classes found; skipping.');
            return;
        }

        $seniorStudentIds = DB::table('student_academic_histories')
            ->where('school_id', $schoolId)
            ->whereIn('class_id', $classIds)
            ->limit(20)
            ->pluck('student_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($seniorStudentIds)) {
            $this->command->info('AlumniSeeder: no senior students found; skipping.');
            return;
        }

        $occupations = [
            'Software Engineer',
            'Doctor (MBBS)',
            'Chartered Accountant',
            'Civil Engineer',
            'Marketing Manager',
            'Banker',
            'Teacher',
            'Architect',
            'Mechanical Engineer',
            'Pharmacist',
            'Lawyer',
            'Researcher',
            'Entrepreneur',
            'Government Officer',
            'Journalist',
        ];

        $employers = [
            'TCS', 'Infosys', 'Wipro', 'Reliance Industries', 'HDFC Bank', 'ICICI Bank',
            'AIIMS Delhi', 'Apollo Hospitals', 'Self-employed', 'L&T Construction',
            'Deloitte India', 'EY India', 'Bharti Airtel', 'IBM India',
        ];

        $cities = ['Bengaluru', 'Mumbai', 'New Delhi', 'Hyderabad', 'Chennai', 'Pune', 'Kolkata', 'Ahmedabad', 'Gurugram', 'Noida'];
        $states = ['Karnataka', 'Maharashtra', 'Delhi', 'Telangana', 'Tamil Nadu', 'Maharashtra', 'West Bengal', 'Gujarat', 'Haryana', 'Uttar Pradesh'];

        $rows = [];
        foreach ($seniorStudentIds as $i => $sid) {
            $student = DB::table('students')->where('id', $sid)->first();
            if (!$student) continue;

            $passoutYear = (int)$now->format('Y') - rand(1, 5);
            $cityIdx = array_rand($cities);

            $rows[] = [
                'school_id'          => $schoolId,
                'student_id'         => $sid,
                'academic_year_id'   => $academicYearId,
                'final_class'        => 'Class ' . [10, 11, 12][array_rand([10, 11, 12])],
                'passout_year'       => $passoutYear . '-' . substr((string)($passoutYear + 1), 2),
                'final_percentage'   => rand(60, 98) + (rand(0, 99) / 100),
                'final_grade'        => ['A1', 'A2', 'B1', 'B2', 'A1'][array_rand(['A1', 'A2', 'B1', 'B2', 'A1'])],
                'current_occupation' => $occupations[array_rand($occupations)],
                'current_employer'   => $employers[array_rand($employers)],
                'current_city'       => $cities[$cityIdx],
                'current_state'      => $states[$cityIdx],
                'personal_email'     => strtolower($student->first_name . '.' . ($student->last_name ?: 'a')) . $i . '@gmail.com',
                'personal_phone'     => '9' . rand(100000000, 999999999),
                'linkedin_url'       => 'https://linkedin.com/in/' . strtolower($student->first_name . '-' . ($student->last_name ?: 'alum')) . '-' . $i,
                'achievements'       => 'Excelled in academics; participated in inter-school events.',
                'notes'               => null,
                'graduated_on'       => Carbon::create($passoutYear, 3, 31)->format('Y-m-d'),
                'graduated_by'       => $adminUserId,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }
        if ($rows) {
            DB::table('alumni')->insert($rows);
        }

        $this->command->info('✅ Alumni seeded: ' . count($rows) . ' alumni records.');
    }
}
