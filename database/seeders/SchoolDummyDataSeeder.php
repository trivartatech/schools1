<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SchoolDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = 1; // school1
        $now = Carbon::now();

        // ── 0. Clear existing data to prevent unique constraint errors ────
        Schema::disableForeignKeyConstraints();

        DB::table('class_subjects')->truncate();
        DB::table('subjects')->truncate();
        DB::table('subject_types')->truncate();
        DB::table('sections')->truncate();
        DB::table('course_classes')->truncate();
        DB::table('departments')->truncate();
        DB::table('periods')->truncate();
        DB::table('holidays')->truncate();
        DB::table('student_academic_histories')->truncate();
        DB::table('students')->truncate();
        DB::table('parents')->truncate();
        DB::table('academic_years')->where('school_id', $schoolId)->delete();

        Schema::enableForeignKeyConstraints();

        // ── 1. Academic Year ──────────────────────────────────────────────────
        $currentYearStart = $now->month >= 4 ? $now->year : $now->year - 1;

        $pastYearStart = $currentYearStart - 1;
        $futureYearStart = $currentYearStart + 1;

        // Past Year (Read-Only)
        DB::table('academic_years')->insert([
            'school_id'  => $schoolId,
            'name'       => $pastYearStart . '-' . substr($pastYearStart + 1, 2),
            'start_date' => $pastYearStart . '-04-01',
            'end_date'   => ($pastYearStart + 1) . '-03-31',
            'is_current' => false,
            'status'     => 'frozen',
            'created_at' => clone $now,
            'updated_at' => clone $now,
        ]);

        // Current Year
        $yearId = DB::table('academic_years')->insertGetId([
            'school_id'  => $schoolId,
            'name'       => $currentYearStart . '-' . substr($currentYearStart + 1, 2),
            'start_date' => $currentYearStart . '-04-01',
            'end_date'   => ($currentYearStart + 1) . '-03-31',
            'is_current' => true,
            'status'     => 'active',
            'created_at' => clone $now,
            'updated_at' => clone $now,
        ]);

        // Future Year
        DB::table('academic_years')->insert([
            'school_id'  => $schoolId,
            'name'       => $futureYearStart . '-' . substr($futureYearStart + 1, 2),
            'start_date' => $futureYearStart . '-04-01',
            'end_date'   => ($futureYearStart + 1) . '-03-31',
            'is_current' => false,
            'status'     => 'draft',
            'created_at' => clone $now,
            'updated_at' => clone $now,
        ]);

        // ── 2. Departments ────────────────────────────────────────────────────
        $depts = [
            ['name' => 'Primary',      'type' => 'Academic'],
            ['name' => 'Middle',       'type' => 'Academic'],
            ['name' => 'Secondary',    'type' => 'Academic'],
            ['name' => 'Sr Secondary', 'type' => 'Academic'],
        ];
        $deptIds = [];
        foreach ($depts as $d) {
            $deptIds[$d['name']] = DB::table('departments')->insertGetId(array_merge($d, [
                'school_id' => $schoolId, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }

        // ── 3. Classes ────────────────────────────────────────────────────────
        $classData = [
            ['name' => 'Class 1',  'dept' => 'Primary',      'num' => 1],
            ['name' => 'Class 2',  'dept' => 'Primary',      'num' => 2],
            ['name' => 'Class 3',  'dept' => 'Primary',      'num' => 3],
            ['name' => 'Class 4',  'dept' => 'Primary',      'num' => 4],
            ['name' => 'Class 5',  'dept' => 'Primary',      'num' => 5],
            ['name' => 'Class 6',  'dept' => 'Middle',       'num' => 6],
            ['name' => 'Class 7',  'dept' => 'Middle',       'num' => 7],
            ['name' => 'Class 8',  'dept' => 'Middle',       'num' => 8],
            ['name' => 'Class 9',  'dept' => 'Secondary',    'num' => 9],
            ['name' => 'Class 10', 'dept' => 'Secondary',    'num' => 10],
            ['name' => 'Class 11', 'dept' => 'Sr Secondary', 'num' => 11],
            ['name' => 'Class 12', 'dept' => 'Sr Secondary', 'num' => 12],
        ];
        $classIds = [];
        foreach ($classData as $c) {
            $classIds[$c['name']] = DB::table('course_classes')->insertGetId([
                'school_id'     => $schoolId,
                'department_id' => $deptIds[$c['dept']],
                'name'          => $c['name'],
                'numeric_value' => $c['num'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        // ── 4. Sections ───────────────────────────────────────────────────────
        $sectionLabels = ['A', 'B', 'C'];
        foreach ($classIds as $className => $classId) {
            $sectionCount = 3;
            for ($i = 0; $i < $sectionCount; $i++) {
                DB::table('sections')->insert([
                    'school_id'       => $schoolId,
                    'course_class_id' => $classId,
                    'name'            => $sectionLabels[$i],
                    'capacity'        => 40,
                    'sort_order'      => $i + 1,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }

        // ── 4b. Bind every section to the current academic year ───────────────
        // The Section model has scopeForCurrentYear() which filters by the
        // section_academic_year pivot. Without this, sections look "unconnected"
        // in the UI even though students/classes reference them correctly.
        $allSectionIds = DB::table('sections')->where('school_id', $schoolId)->pluck('id');
        $pivotRows = $allSectionIds->map(fn($sid) => [
            'section_id'       => $sid,
            'academic_year_id' => $yearId,
            'created_at'       => $now,
            'updated_at'       => $now,
        ])->all();
        if (!empty($pivotRows)) {
            DB::table('section_academic_year')->insertOrIgnore($pivotRows);
        }

        // ── 5. Subject Types ─────────────────────────────────────────────────
        $partTypes = [
            ['label' => 'Part A',          'description' => 'First part / theory component',               'sort_order' => 1],
            ['label' => 'Part B',          'description' => 'Second part / practical / oral',              'sort_order' => 2],
            ['label' => 'Language 1',      'description' => 'First language (e.g. Hindi/English)',         'sort_order' => 3],
            ['label' => 'Language 2',      'description' => 'Second language',                             'sort_order' => 4],
            ['label' => 'Elective Group',  'description' => 'Student-selected elective subject',           'sort_order' => 5],
        ];
        $subjectTypeIds = [];
        foreach ($partTypes as $pt) {
            $subjectTypeIds[$pt['label']] = DB::table('subject_types')->insertGetId(array_merge($pt, [
                'school_id' => $schoolId, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }

        // ── 6. Subjects ───────────────────────────────────────────────────────
        $subjects = [
            ['name' => 'English',           'code' => 'ENG',    'type' => 'theory',    'part' => 'Language 1', 'is_co_scholastic' => false, 'is_elective' => false, 'sort_order' => 1],
            ['name' => 'Hindi',             'code' => 'HIN',    'type' => 'theory',    'part' => 'Language 2', 'is_co_scholastic' => false, 'is_elective' => false, 'sort_order' => 2],
            ['name' => 'Mathematics',       'code' => 'MAT',    'type' => 'theory',    'part' => null,         'is_co_scholastic' => false, 'is_elective' => false, 'sort_order' => 3],
            ['name' => 'Science',           'code' => 'SCI',    'type' => 'theory',    'part' => 'Part A',     'is_co_scholastic' => false, 'is_elective' => false, 'sort_order' => 4],
            ['name' => 'Science Lab',       'code' => 'SCILAB', 'type' => 'practical', 'part' => 'Part B',     'is_co_scholastic' => false, 'is_elective' => false, 'sort_order' => 5],
            ['name' => 'Social Science',    'code' => 'SST',    'type' => 'theory',    'part' => null,         'is_co_scholastic' => false, 'is_elective' => false, 'sort_order' => 6],
            ['name' => 'Computer Science',  'code' => 'CS',     'type' => 'theory',    'part' => 'Elective Group', 'is_co_scholastic' => false, 'is_elective' => true,  'sort_order' => 7],
            ['name' => 'Physical Education','code' => 'PE',     'type' => 'practical', 'part' => null,         'is_co_scholastic' => true,  'is_elective' => false, 'sort_order' => 8],
            ['name' => 'Art & Craft',       'code' => 'ART',    'type' => 'practical', 'part' => null,         'is_co_scholastic' => true,  'is_elective' => false, 'sort_order' => 9],
            ['name' => 'Music',             'code' => 'MUS',    'type' => 'practical', 'part' => null,         'is_co_scholastic' => true,  'is_elective' => true,  'sort_order' => 10],
            ['name' => 'Physics',           'code' => 'PHY',    'type' => 'theory',    'part' => 'Part A',     'is_co_scholastic' => false, 'is_elective' => false, 'sort_order' => 11],
            ['name' => 'Chemistry',         'code' => 'CHE',    'type' => 'theory',    'part' => 'Part A',     'is_co_scholastic' => false, 'is_elective' => false, 'sort_order' => 12],
            ['name' => 'Biology',           'code' => 'BIO',    'type' => 'theory',    'part' => 'Part A',     'is_co_scholastic' => false, 'is_elective' => true,  'sort_order' => 13],
            ['name' => 'Accountancy',       'code' => 'ACC',    'type' => 'theory',    'part' => 'Elective Group', 'is_co_scholastic' => false, 'is_elective' => true,  'sort_order' => 14],
            ['name' => 'Economics',         'code' => 'ECO',    'type' => 'theory',    'part' => 'Elective Group', 'is_co_scholastic' => false, 'is_elective' => true,  'sort_order' => 15],
        ];
        $subjectIds = [];
        foreach ($subjects as $s) {
            $typeId = isset($s['part']) && $s['part'] ? ($subjectTypeIds[$s['part']] ?? null) : null;
            $subjectIds[$s['name']] = DB::table('subjects')->insertGetId(array_merge($s, [
                'school_id'       => $schoolId,
                'subject_type_id' => $typeId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]));
        }

        // ── 7. Class-Subject Assignments ──────────────────────────────────────
        // Class 5 — Primary
        $class5Id = $classIds['Class 5'];
        foreach (['English', 'Hindi', 'Mathematics', 'Science', 'Science Lab', 'Social Science', 'Physical Education', 'Art & Craft'] as $sub) {
            DB::table('class_subjects')->insert([
                'school_id'        => $schoolId,
                'course_class_id'  => $class5Id,
                'section_id'       => null,
                'subject_id'       => $subjectIds[$sub],
                'is_co_scholastic' => in_array($sub, ['Physical Education', 'Art & Craft']),
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
        // Class 10 — Secondary
        $class10Id = $classIds['Class 10'];
        foreach (['English', 'Hindi', 'Mathematics', 'Science', 'Science Lab', 'Social Science', 'Computer Science', 'Physical Education'] as $sub) {
            DB::table('class_subjects')->insert([
                'school_id'        => $schoolId,
                'course_class_id'  => $class10Id,
                'section_id'       => null,
                'subject_id'       => $subjectIds[$sub],
                'is_co_scholastic' => in_array($sub, ['Physical Education']),
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
        // Class 11 & 12 — Sr Secondary (Science stream)
        foreach (['Class 11', 'Class 12'] as $cn) {
            foreach (['English', 'Physics', 'Chemistry', 'Mathematics', 'Biology', 'Physical Education'] as $sub) {
                DB::table('class_subjects')->insert([
                    'school_id'        => $schoolId,
                    'course_class_id'  => $classIds[$cn],
                    'section_id'       => null,
                    'subject_id'       => $subjectIds[$sub],
                    'is_co_scholastic' => $sub === 'Physical Education',
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }

        // ── 8. Periods ────────────────────────────────────────────────────────
        $periods = [
            ['name' => 'Assembly',       'start_time' => '07:45', 'end_time' => '08:00', 'type' => 'assembly', 'is_weekend' => false, 'order' => 1],
            ['name' => 'Period 1',       'start_time' => '08:00', 'end_time' => '08:45', 'type' => 'period',   'is_weekend' => false, 'order' => 2],
            ['name' => 'Period 2',       'start_time' => '08:45', 'end_time' => '09:30', 'type' => 'period',   'is_weekend' => false, 'order' => 3],
            ['name' => 'Period 3',       'start_time' => '09:30', 'end_time' => '10:15', 'type' => 'period',   'is_weekend' => false, 'order' => 4],
            ['name' => 'Short Break',    'start_time' => '10:15', 'end_time' => '10:30', 'type' => 'break',    'is_weekend' => false, 'order' => 5],
            ['name' => 'Period 4',       'start_time' => '10:30', 'end_time' => '11:15', 'type' => 'period',   'is_weekend' => false, 'order' => 6],
            ['name' => 'Period 5',       'start_time' => '11:15', 'end_time' => '12:00', 'type' => 'period',   'is_weekend' => false, 'order' => 7],
            ['name' => 'Lunch Break',    'start_time' => '12:00', 'end_time' => '12:40', 'type' => 'lunch',    'is_weekend' => false, 'order' => 8],
            ['name' => 'Period 6',       'start_time' => '12:40', 'end_time' => '13:25', 'type' => 'period',   'is_weekend' => false, 'order' => 9],
            ['name' => 'Period 7',       'start_time' => '13:25', 'end_time' => '14:10', 'type' => 'period',   'is_weekend' => false, 'order' => 10],
            ['name' => 'Assembly',       'start_time' => '08:00', 'end_time' => '08:15', 'type' => 'assembly', 'is_weekend' => true,  'order' => 1],
            ['name' => 'Extra Class 1',  'start_time' => '08:15', 'end_time' => '09:00', 'type' => 'period',   'is_weekend' => true,  'order' => 2],
            ['name' => 'Extra Class 2',  'start_time' => '09:00', 'end_time' => '09:45', 'type' => 'period',   'is_weekend' => true,  'order' => 3],
            ['name' => 'Recess',         'start_time' => '09:45', 'end_time' => '10:00', 'type' => 'break',    'is_weekend' => true,  'order' => 4],
            ['name' => 'Club Activity',  'start_time' => '10:00', 'end_time' => '11:30', 'type' => 'period',   'is_weekend' => true,  'order' => 5],
        ];
        foreach ($periods as $p) {
            DB::table('periods')->insert(array_merge($p, [
                'school_id' => $schoolId, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }

        // ── 9. Holidays & Events ─────────────────────────────────────────────
        $holidays = [
            ['title' => 'Republic Day',          'date' => '2025-01-26', 'end_date' => null,         'type' => 'holiday', 'description' => 'National holiday'],
            ['title' => 'Holi',                  'date' => '2025-03-14', 'end_date' => '2025-03-15', 'type' => 'holiday', 'description' => 'Festival of colors'],
            ['title' => 'Annual Sports Day',     'date' => '2025-02-08', 'end_date' => null,         'type' => 'event',   'description' => 'School sports meet'],
            ['title' => 'Board Exams Begin',     'date' => '2025-02-15', 'end_date' => '2025-03-31', 'type' => 'exam',    'description' => 'Class 10 & 12 board exams'],
            ['title' => 'Annual Day',            'date' => '2025-01-18', 'end_date' => null,         'type' => 'event',   'description' => 'School annual function'],
            ['title' => 'Independence Day',      'date' => '2024-08-15', 'end_date' => null,         'type' => 'holiday', 'description' => 'National holiday'],
            ['title' => 'Diwali Holidays',       'date' => '2024-10-31', 'end_date' => '2024-11-05', 'type' => 'holiday', 'description' => 'Festival break'],
            ['title' => 'Parent-Teacher Meeting','date' => '2024-12-07', 'end_date' => null,         'type' => 'event',   'description' => 'Class-wise PTM'],
        ];
        foreach ($holidays as $h) {
            DB::table('holidays')->insert(array_merge($h, [
                'school_id' => $schoolId, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }

        // ── 10. Parents & Students ────────────────────────────────────────────
        $adultFirstNamesM = ['Rajesh', 'Suresh', 'Amit', 'Vikram', 'Anil', 'Sanjay', 'Mukesh', 'Ramesh', 'Rakesh', 'Vijay', 'Arvind', 'Prakash', 'Deepak', 'Manoj'];
        $adultFirstNamesF = ['Sunita', 'Geeta', 'Anita', 'Kavita', 'Meena', 'Rekha', 'Pooja', 'Neha', 'Seema', 'Anjali', 'Kiran', 'Aarti', 'Manju', 'Rita'];
        $lastNames        = ['Sharma', 'Verma', 'Singh', 'Patel', 'Kumar', 'Gupta', 'Yadav', 'Rao', 'Shah', 'Joshi', 'Chauhan', 'Mehta', 'Nair', 'Reddy'];
        $cities           = ['Koramangala, Bengaluru', 'Andheri West, Mumbai', 'Connaught Place, New Delhi', 'Salt Lake, Kolkata', 'Banjara Hills, Hyderabad', 'Anna Nagar, Chennai', 'Viman Nagar, Pune', 'Navrangpura, Ahmedabad'];
        $occupations      = ['Engineer', 'Doctor', 'Teacher', 'Businessman', 'Lawyer', 'Accountant', 'Banker', 'Government Employee', 'Architect', 'Pharmacist'];
        $prefixes         = ['98', '99', '88', '77', '70', '96', '91'];

        $parentIds = [];
        for ($i = 1; $i <= 100; $i++) {
            $lastName      = $lastNames[array_rand($lastNames)];
            $fatherName    = $adultFirstNamesM[array_rand($adultFirstNamesM)] . ' ' . $lastName;
            $motherName    = $adultFirstNamesF[array_rand($adultFirstNamesF)] . ' ' . $lastName;
            $primaryPhone  = $prefixes[array_rand($prefixes)] . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            $fatherPhone   = $prefixes[array_rand($prefixes)] . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            $motherPhone   = $prefixes[array_rand($prefixes)] . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            $address       = rand(10, 999) . ', ' . $cities[array_rand($cities)];

            $parentIds[] = DB::table('parents')->insertGetId([
                'school_id'          => $schoolId,
                'father_name'        => $fatherName,
                'mother_name'        => $motherName,
                'primary_phone'      => $primaryPhone,
                'father_phone'       => $fatherPhone,
                'mother_phone'       => $motherPhone,
                'father_occupation'  => $occupations[array_rand($occupations)],
                'mother_occupation'  => $occupations[array_rand($occupations)],
                'address'            => $address,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }

        $boyNames   = ['Aarav', 'Vihaan', 'Arjun', 'Sai', 'Krishna', 'Rishabh', 'Aditya', 'Vivaan', 'Kabir', 'Aaryan', 'Rudra', 'Dhruv', 'Reyansh', 'Ayush'];
        $girlNames  = ['Diya', 'Sanya', 'Aanya', 'Kiara', 'Myra', 'Ananya', 'Navya', 'Avni', 'Riya', 'Ishita', 'Sneha', 'Tanya', 'Prisha', 'Kritika'];
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
        $religions   = ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist', 'Jain'];

        $studentCounter = 1;

        foreach ($classIds as $className => $classId) {
            $sections = DB::table('sections')->where('course_class_id', $classId)->get();

            foreach ($sections as $section) {
                // Class 5 Section A gets exactly 1 student; every other section gets 10
                $studentsToAdd = ($className === 'Class 5' && $section->name === 'A') ? 1 : 10;

                for ($s = 1; $s <= $studentsToAdd; $s++) {
                    $pid            = $parentIds[array_rand($parentIds)];
                    $parentRecord   = DB::table('parents')->where('id', $pid)->first();
                    $parts          = explode(' ', $parentRecord->father_name);
                    $familyLastName = end($parts);

                    $isGirl    = ($studentCounter % 2 == 0);
                    $firstName = $isGirl ? $girlNames[array_rand($girlNames)] : $boyNames[array_rand($boyNames)];

                    $emergencyPhone = $prefixes[array_rand($prefixes)] . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);

                    $studentId = DB::table('students')->insertGetId([
                        'school_id'               => $schoolId,
                        'parent_id'               => $pid,
                        'admission_no'            => 'ADM' . date('Y') . str_pad($studentCounter, 4, '0', STR_PAD_LEFT),
                        'erp_no'                  => 'ERP' . str_pad($studentCounter, 5, '0', STR_PAD_LEFT),
                        'roll_no'                 => $s,
                        'first_name'              => $firstName,
                        'last_name'               => $familyLastName,
                        'dob'                     => Carbon::now()->subYears(5 + intval(preg_replace('/[^0-9]/', '', $className)))->format('Y-m-d'),
                        'gender'                  => $isGirl ? 'Female' : 'Male',
                        'blood_group'             => $bloodGroups[array_rand($bloodGroups)],
                        'religion'                => $religions[array_rand($religions)],
                        'address'                 => $parentRecord->address,
                        'emergency_contact_name'  => $parentRecord->father_name,
                        'emergency_contact_phone' => $emergencyPhone,
                        'admission_date'          => $now->format('Y-m-d'),
                        'status'                  => 'active',
                        'created_at'              => $now,
                        'updated_at'              => $now,
                    ]);

                    DB::table('student_academic_histories')->insert([
                        'school_id'        => $schoolId,
                        'student_id'       => $studentId,
                        'academic_year_id' => $yearId,
                        'class_id'         => $classId,
                        'section_id'       => $section->id,
                        'roll_no'          => $s,
                        'status'           => 'current',
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);

                    $studentCounter++;
                }
            }
        }

        // ── 11. Leave Types ───────────────────────────────────────────────────
        $leaveTypes = [
            ['name' => 'Casual Leave',    'code' => 'CL',  'days_allowed' => 12, 'color' => '#54aeff', 'is_paid' => true,  'carry_forward' => false, 'requires_document' => false, 'min_notice_days' => 1,  'applicable_to' => 'both',     'sort_order' => 1, 'description' => 'General purpose short leave for personal reasons'],
            ['name' => 'Sick Leave',      'code' => 'SL',  'days_allowed' => 10, 'color' => '#f0883e', 'is_paid' => true,  'carry_forward' => false, 'requires_document' => true,  'min_notice_days' => 0,  'applicable_to' => 'both',     'sort_order' => 2, 'description' => 'Leave on account of illness; medical certificate required for 3+ days'],
            ['name' => 'Earned Leave',    'code' => 'EL',  'days_allowed' => 15, 'color' => '#2da44e', 'is_paid' => true,  'carry_forward' => true,  'requires_document' => false, 'min_notice_days' => 3,  'applicable_to' => 'both',     'sort_order' => 3, 'description' => 'Accrued paid leave earned through service'],
            ['name' => 'Maternity Leave', 'code' => 'ML',  'days_allowed' => 90, 'color' => '#d2a8ff', 'is_paid' => true,  'carry_forward' => false, 'requires_document' => true,  'min_notice_days' => 30, 'applicable_to' => 'staff',  'sort_order' => 4, 'description' => 'Maternity leave as per Maternity Benefit Act'],
            ['name' => 'Unpaid Leave',    'code' => 'LWP', 'days_allowed' => 30, 'color' => '#da3633', 'is_paid' => false, 'carry_forward' => false, 'requires_document' => false, 'min_notice_days' => 1,  'applicable_to' => 'both',     'sort_order' => 5, 'description' => 'Leave without pay — salary deducted for days taken'],
            ['name' => 'Special Leave',   'code' => 'SPL', 'days_allowed' => 5,  'color' => '#a371f7', 'is_paid' => true,  'carry_forward' => false, 'requires_document' => false, 'min_notice_days' => 0,  'applicable_to' => 'both',     'sort_order' => 6, 'description' => 'Discretionary leave granted by management for exceptional circumstances'],
        ];

        foreach ($leaveTypes as $lt) {
            DB::table('leave_types')->insert(array_merge($lt, [
                'school_id'  => $schoolId,
                'is_active'  => true,
                'max_carry_forward_days' => $lt['carry_forward'] ? 10 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->command->info('✅ Dummy data seeded: All 12 classes × 3 sections | Class 5-A → 1 student, all others → 10 students');
    }
}
