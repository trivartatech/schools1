<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\FeeGroup;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\FeePayment;
use App\Models\CourseClass;
use App\Models\Student;
use App\Models\AcademicYear;

class FeeDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = 1; // Assuming DPS North Campus
        $now = Carbon::now();

        // Get the active academic year
        $academicYear = AcademicYear::where('school_id', $schoolId)->where('status', 'active')->first();
        if (!$academicYear) {
            $this->command->error('No active academic year found for school_id=' . $schoolId);
            return;
        }

        // ── 1. Create Fee Groups ──────────────────────────────────────────────
        $groups = [
            ['name' => 'General Fees', 'description' => 'Standard composite fees'],
            ['name' => 'Transport Fees', 'description' => 'Optional bus fees'],
            ['name' => 'Hostel Fees', 'description' => 'Optional boarding fees'],
            ['name' => 'New Admission', 'description' => 'One-time admission charges'],
        ];

        $groupIds = [];
        foreach ($groups as $g) {
            $groupIds[$g['name']] = DB::table('fee_groups')->insertGetId(array_merge($g, [
                'school_id' => $schoolId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // ── 2. Create Fee Heads ───────────────────────────────────────────────
        $heads = [
            ['group' => 'General Fees', 'name' => 'Tuition Fee', 'short_code' => 'TUI', 'description' => 'Monthly tuition fee'],
            ['group' => 'General Fees', 'name' => 'Computer Fee', 'short_code' => 'COMP', 'description' => 'Monthly computer fee'],
            ['group' => 'General Fees', 'name' => 'Activity Fee', 'short_code' => 'ACT', 'description' => 'Quarterly activity fee'],
            ['group' => 'New Admission', 'name' => 'Admission Fee', 'short_code' => 'ADM', 'description' => 'One-time admission fee'],
            ['group' => 'Transport Fees', 'name' => 'Bus Fee', 'short_code' => 'BUS', 'description' => 'Monthly transport fee'],
        ];

        $headIds = [];
        foreach ($heads as $h) {
            $groupId = $groupIds[$h['group']];
            unset($h['group']);
            $headIds[$h['name']] = DB::table('fee_heads')->insertGetId(array_merge($h, [
                'school_id' => $schoolId,
                'fee_group_id' => $groupId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // ── 3. Create Fee Structures ──────────────────────────────────────────
        $classes = CourseClass::where('school_id', $schoolId)->get();
        $terms = ['Installment 1', 'Installment 2', 'Installment 3'];

        foreach ($classes as $class) {
            // Apply Admission Fee (One term)
            DB::table('fee_structures')->insert([
                'school_id' => $schoolId,
                'academic_year_id' => $academicYear->id,
                'class_id' => $class->id,
                'fee_head_id' => $headIds['Admission Fee'],
                'amount' => 15000.00,
                'term' => 'Admission Installment',
                'due_date' => Carbon::parse($academicYear->start_date)->addDays(15),
                'student_type' => 'new', // Only new students
                'gender' => 'all',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Apply Tuition Fee (3 Terms)
            foreach ($terms as $index => $term) {
                DB::table('fee_structures')->insert([
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYear->id,
                    'class_id' => $class->id,
                    'fee_head_id' => $headIds['Tuition Fee'],
                    'amount' => 6000.00,
                    'term' => $term,
                    'due_date' => Carbon::parse($academicYear->start_date)->addMonths($index * 4)->addDays(10),
                    'student_type' => 'all',
                    'gender' => 'all',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // ── 4. Create Fee Payments (Realistic Analyzed Data) ────────────────────────
        $students = clone Student::where('school_id', $schoolId)->get();
        
        $tuitionHead = $headIds['Tuition Fee'];

        $receiptCounter = 1;

        $concessionsToAssign = [
            ['name' => 'Sibling Discount', 'type' => 'percentage', 'value' => 10.00],
            ['name' => 'Staff Ward', 'type' => 'percentage', 'value' => 50.00],
            ['name' => 'Merit Scholarship', 'type' => 'fixed', 'value' => 1000.00],
        ];

        foreach ($students as $student) {
            $paymentScenario = rand(1, 10); // 1-6 = Good Payer, 7-8 = Partial Payer, 9 = Late Payer, 10 = Defaulter

            // 15% chance to have a concession
            $hasConcession = rand(1, 100) <= 15;
            $concessionId = null;
            $concessionAmount = 0;
            $concessionNote = null;

            if ($hasConcession) {
                $c = $concessionsToAssign[array_rand($concessionsToAssign)];
                $concessionId = DB::table('fee_concessions')->insertGetId([
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYear->id,
                    'student_id' => $student->id,
                    'name' => $c['name'],
                    'type' => $c['type'],
                    'value' => $c['value'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($c['type'] == 'percentage') {
                    $concessionAmount = 6000.00 * ($c['value'] / 100);
                } else {
                    $concessionAmount = $c['value'];
                }
                $concessionNote = $c['name'];
            }

            $dueAmount = 6000.00;
            $discount = $concessionAmount;
            $netPayable = $dueAmount - $discount;

            if ($paymentScenario <= 6) {
                // Scenario 1: Good Payer - Pays Installment 1 and 2 fully and on time
                FeePayment::create([
                    'receipt_no' => 'FEE-2026-' . str_pad($receiptCounter++, 5, '0', STR_PAD_LEFT),
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'fee_head_id' => $tuitionHead,
                    'amount_due' => $dueAmount,
                    'amount_paid' => $netPayable,
                    'discount' => $discount,
                    'concession_id' => $concessionId,
                    'concession_note' => $concessionNote,
                    'fine' => 0,
                    'term' => 'Installment 1',
                    'payment_date' => Carbon::parse($academicYear->start_date)->addDays(rand(1, 15)),
                    'payment_mode' => 'online',
                    'status' => 'paid',
                    'collected_by' => 1,
                ]);

                FeePayment::create([
                    'receipt_no' => 'FEE-2026-' . str_pad($receiptCounter++, 5, '0', STR_PAD_LEFT),
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'fee_head_id' => $tuitionHead,
                    'amount_due' => $dueAmount,
                    'amount_paid' => $netPayable,
                    'discount' => $discount,
                    'concession_id' => $concessionId,
                    'concession_note' => $concessionNote,
                    'fine' => 0,
                    'term' => 'Installment 2',
                    'payment_date' => Carbon::parse($academicYear->start_date)->addMonths(4)->addDays(rand(1, 15)),
                    'payment_mode' => 'online',
                    'status' => 'paid',
                    'collected_by' => 1,
                ]);
            } elseif ($paymentScenario <= 8) {
                // Scenario 2: Partial Payer - Paid Installment 1 fully, paid Installment 2 partially
                FeePayment::create([
                    'receipt_no' => 'FEE-2026-' . str_pad($receiptCounter++, 5, '0', STR_PAD_LEFT),
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'fee_head_id' => $tuitionHead,
                    'amount_due' => $dueAmount,
                    'amount_paid' => $netPayable,
                    'discount' => $discount,
                    'concession_id' => $concessionId,
                    'concession_note' => $concessionNote,
                    'fine' => 0,
                    'term' => 'Installment 1',
                    'payment_date' => Carbon::parse($academicYear->start_date)->addDays(rand(1, 20)),
                    'payment_mode' => 'cash',
                    'status' => 'paid',
                    'collected_by' => 1,
                ]);

                $partialPaymentAmount = round($netPayable / 2, 2);

                FeePayment::create([
                    'receipt_no' => 'FEE-2026-' . str_pad($receiptCounter++, 5, '0', STR_PAD_LEFT),
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'fee_head_id' => $tuitionHead,
                    'amount_due' => $dueAmount,
                    'amount_paid' => $partialPaymentAmount, // Partial
                    'discount' => $discount,
                    'concession_id' => $concessionId,
                    'concession_note' => $concessionNote,
                    'fine' => 0,
                    'term' => 'Installment 2',
                    'payment_date' => Carbon::parse($academicYear->start_date)->addMonths(4)->addDays(rand(10, 25)),
                    'payment_mode' => 'cash',
                    'status' => 'partial',
                    'collected_by' => 1,
                ]);
            } elseif ($paymentScenario == 9) {
                // Scenario 3: Late Payer - Paid Installment 1 late with fine
                FeePayment::create([
                    'receipt_no' => 'FEE-2026-' . str_pad($receiptCounter++, 5, '0', STR_PAD_LEFT),
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'fee_head_id' => $tuitionHead,
                    'amount_due' => $dueAmount,
                    'amount_paid' => $netPayable + 200.00, // net + fine
                    'discount' => $discount,
                    'concession_id' => $concessionId,
                    'concession_note' => $concessionNote,
                    'fine' => 200.00,
                    'term' => 'Installment 1',
                    'payment_date' => Carbon::parse($academicYear->start_date)->addMonths(1)->addDays(rand(1, 15)), // Paid a month late
                    'payment_mode' => 'cheque',
                    'status' => 'paid',
                    'collected_by' => 1,
                ]);
            }
            // Scenario 4: Defaulter - Paid nothing yet (Will show up in Due Report)
        }

        $this->command->info('✅ Dummy Fee Data (Groups, Heads, Structures, Payments) seeded successfully!');
    }
}
