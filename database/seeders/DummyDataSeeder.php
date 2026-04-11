<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\School;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\CourseClass;
use App\Models\Attendance;
use App\Models\FeeGroup;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\FeePayment;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::first();
        if (!$school) {
            $this->command->info('No school found. Please seed basic setup first.');
            return;
        }

        $academicYear = AcademicYear::where('school_id', $school->id)->where('status', 'active')->first() 
            ?? AcademicYear::where('school_id', $school->id)->first();
            
        if (!$academicYear) {
            $this->command->info('No academic year found.');
            return;
        }
        $adminId = 1; // Assuming user ID 1 is the admin

        $students = Student::where('school_id', $school->id)->get();
        if ($students->isEmpty()) {
            $this->command->info('No students found. Please create some students first.');
            return;
        }

        // 1. Attendance Data (Past 7 days)
        $this->command->info('Seeding Attendance for past 7 days...');
        $statuses = ['present', 'absent', 'late', 'half_day'];
        foreach ($students as $student) {
            $history = StudentAcademicHistory::where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->first();
            
            if (!$history) continue;

            for ($i = 0; $i < 7; $i++) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d 00:00:00');
                // Only seed weekdays
                if (Carbon::parse($date)->isWeekend()) continue;

                Attendance::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'academic_year_id' => $academicYear->id,
                        'student_id' => $student->id,
                        'date' => $date, // Already in valid sqlite string formats
                    ],
                    [
                        'class_id' => $history->class_id,
                        'section_id' => $history->section_id,
                        'status' => $statuses[array_rand($statuses)],
                        'marked_by' => $adminId,
                    ]
                );
            }
        }

        // 2. Fee Setup
        $this->command->info('Seeding Fee Groups & Heads...');
        $tuitionGroup = FeeGroup::firstOrCreate(['school_id' => $school->id, 'name' => 'Academic Fees']);
        $transportGroup = FeeGroup::firstOrCreate(['school_id' => $school->id, 'name' => 'Transport Fees']);

        $tuitionHead = FeeHead::firstOrCreate([
            'school_id' => $school->id, 
            'fee_group_id' => $tuitionGroup->id,
            'name' => 'Tuition Fee'
        ], ['short_code' => 'TFEE']);

        $examHead = FeeHead::firstOrCreate([
            'school_id' => $school->id, 
            'fee_group_id' => $tuitionGroup->id,
            'name' => 'Examination Fee'
        ], ['short_code' => 'EXFEE']);

        $busHead = FeeHead::firstOrCreate([
            'school_id' => $school->id, 
            'fee_group_id' => $transportGroup->id,
            'name' => 'Bus Fare'
        ], ['short_code' => 'BUS']);

        // 3. Fee Structures
        $this->command->info('Seeding Fee Structures...');
        $classes = CourseClass::where('school_id', $school->id)->get();
        foreach ($classes as $courseClass) {
            // Annual Tuition
            FeeStructure::firstOrCreate([
                'school_id' => $school->id,
                'academic_year_id' => $academicYear->id,
                'class_id' => $courseClass->id,
                'fee_head_id' => $tuitionHead->id,
                'term' => 'annual'
            ], [
                'amount' => 50000,
                'due_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
                'is_optional' => false,
                'student_type' => 'all',
                'gender' => 'all'
            ]);

            // Optional Exam Fee for Term 1
            FeeStructure::firstOrCreate([
                'school_id' => $school->id,
                'academic_year_id' => $academicYear->id,
                'class_id' => $courseClass->id,
                'fee_head_id' => $examHead->id,
                'term' => 'term1'
            ], [
                'amount' => 2500,
                'due_date' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'is_optional' => true,
                'student_type' => 'all',
                'gender' => 'all'
            ]);
            
            // Installment 1 Bus
            FeeStructure::firstOrCreate([
                'school_id' => $school->id,
                'academic_year_id' => $academicYear->id,
                'class_id' => $courseClass->id,
                'fee_head_id' => $busHead->id,
                'term' => 'Installment 1'
            ], [
                'amount' => 1000,
                'due_date' => Carbon::now()->subDays(5)->format('Y-m-d'), // Overdue
                'late_fee_per_day' => 10,
                'is_optional' => false,
                'student_type' => 'all',
                'gender' => 'all'
            ]);
        }

        // 4. Fee Payments
        $this->command->info('Seeding Fee Payments...');
        $modes = ['cash', 'online', 'upi', 'bank_transfer'];
        foreach ($students->take(10) as $student) { // Only do first 10 students
            
            // Partial payment for Tuition
            $receiptNo = 'REC-' . strtoupper(Str::random(6));
            FeePayment::firstOrCreate([
                'school_id' => $school->id,
                'academic_year_id' => $academicYear->id,
                'student_id' => $student->id,
                'fee_head_id' => $tuitionHead->id,
                'term' => 'annual'
            ], [
                'amount_due' => 50000,
                'amount_paid' => 20000,
                'discount' => 0,
                'fine' => 0,
                'balance' => 30000,
                'status' => 'partial',
                'payment_date' => Carbon::now()->subDays(rand(1, 10))->format('Y-m-d'),
                'payment_mode' => $modes[array_rand($modes)],
                'transaction_ref' => 'TXN' . rand(1000, 9999),
                'receipt_no' => $receiptNo,
                'collected_by' => $adminId
            ]);

            // Full payment for Installment 1 bus
            $receiptNo = 'REC-' . strtoupper(Str::random(6));
            FeePayment::firstOrCreate([
                'school_id' => $school->id,
                'academic_year_id' => $academicYear->id,
                'student_id' => $student->id,
                'fee_head_id' => $busHead->id,
                'term' => 'Installment 1'
            ], [
                'amount_due' => 1000,
                'amount_paid' => 1000,
                'discount' => 0,
                'fine' => 0,
                'balance' => 0,
                'status' => 'paid',
                'payment_date' => Carbon::now()->subDays(rand(1, 5))->format('Y-m-d'),
                'payment_mode' => $modes[array_rand($modes)],
                'transaction_ref' => 'TXN' . rand(1000, 9999),
                'receipt_no' => $receiptNo,
                'collected_by' => $adminId
            ]);
        }

        $this->command->info('Dummy data seeded successfully!');
    }
}
