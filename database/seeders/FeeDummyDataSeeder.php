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
use App\Models\School;

class FeeDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        if (!$school) {
            $this->command->error('No school found.');
            return;
        }
        $schoolId = $school->id;
        $now = Carbon::now();

        // Get the active academic year
        $academicYear = AcademicYear::where('school_id', $schoolId)->where('status', 'active')->first();
        if (!$academicYear) {
            $this->command->error('No active academic year found for school_id=' . $schoolId);
            return;
        }

        // ── 1. Create Fee Groups ──────────────────────────────────────────────
        $groups = [
            ['name' => 'General Fees',  'description' => 'Standard composite fees'],
            ['name' => 'Transport Fees','description' => 'Optional bus fees'],
            ['name' => 'Hostel Fees',   'description' => 'Optional boarding fees'],
            ['name' => 'New Admission', 'description' => 'One-time admission charges'],
        ];

        $groupIds = [];
        foreach ($groups as $g) {
            $group = FeeGroup::firstOrCreate(
                ['school_id' => $schoolId, 'name' => $g['name']],
                ['description' => $g['description']]
            );
            $groupIds[$g['name']] = $group->id;
        }

        // ── 2. Create Fee Heads ───────────────────────────────────────────────
        $heads = [
            ['group' => 'General Fees',  'name' => 'Tuition Fee',   'short_code' => 'TUI',  'description' => 'Monthly tuition fee'],
            ['group' => 'General Fees',  'name' => 'Computer Fee',  'short_code' => 'COMP', 'description' => 'Monthly computer fee'],
            ['group' => 'General Fees',  'name' => 'Activity Fee',  'short_code' => 'ACT',  'description' => 'Quarterly activity fee'],
            ['group' => 'New Admission', 'name' => 'Admission Fee', 'short_code' => 'ADM',  'description' => 'One-time admission fee'],
            ['group' => 'Transport Fees','name' => 'Bus Fee',        'short_code' => 'BUS',  'description' => 'Monthly transport fee'],
        ];

        $headIds = [];
        foreach ($heads as $h) {
            $groupId = $groupIds[$h['group']];
            $head = FeeHead::firstOrCreate(
                ['school_id' => $schoolId, 'fee_group_id' => $groupId, 'name' => $h['name']],
                ['short_code' => $h['short_code'], 'description' => $h['description']]
            );
            $headIds[$h['name']] = $head->id;
        }

        // ── 3. Create Fee Structures ──────────────────────────────────────────
        $classes = CourseClass::where('school_id', $schoolId)->get();
        $terms = ['Installment 1', 'Installment 2', 'Installment 3'];

        foreach ($classes as $class) {
            FeeStructure::firstOrCreate(
                [
                    'school_id'        => $schoolId,
                    'academic_year_id' => $academicYear->id,
                    'class_id'         => $class->id,
                    'fee_head_id'      => $headIds['Admission Fee'],
                    'term'             => 'Admission Installment',
                ],
                [
                    'amount'       => 15000.00,
                    'due_date'     => Carbon::parse($academicYear->start_date)->addDays(15),
                    'student_type' => 'new',
                    'gender'       => 'all',
                ]
            );

            foreach ($terms as $index => $term) {
                FeeStructure::firstOrCreate(
                    [
                        'school_id'        => $schoolId,
                        'academic_year_id' => $academicYear->id,
                        'class_id'         => $class->id,
                        'fee_head_id'      => $headIds['Tuition Fee'],
                        'term'             => $term,
                    ],
                    [
                        'amount'       => 6000.00,
                        'due_date'     => Carbon::parse($academicYear->start_date)->addMonths($index * 4)->addDays(10),
                        'student_type' => 'all',
                        'gender'       => 'all',
                    ]
                );
            }
        }

        // ── 4. Create Fee Payments ────────────────────────────────────────────
        $students = Student::where('school_id', $schoolId)->get();

        // Build a set of student IDs that already have payments this year (O(1) lookup).
        $paidStudentIds = array_flip(
            FeePayment::where('school_id', $schoolId)
                ->where('academic_year_id', $academicYear->id)
                ->distinct()
                ->pluck('student_id')
                ->all()
        );

        $tuitionHead = $headIds['Tuition Fee'];

        $concessionsToAssign = [
            ['name' => 'Sibling Discount', 'type' => 'percentage', 'value' => 10.00],
            ['name' => 'Staff Ward',        'type' => 'percentage', 'value' => 50.00],
            ['name' => 'Merit Scholarship', 'type' => 'fixed',      'value' => 1000.00],
        ];

        foreach ($students as $student) {
            // Skip students that already have payment records for this academic year.
            if (array_key_exists($student->id, $paidStudentIds)) {
                continue;
            }

            $paymentScenario = rand(1, 10); // 1-6 = Good Payer, 7-8 = Partial Payer, 9 = Late Payer, 10 = Defaulter

            // 15% chance to have a concession
            $hasConcession = rand(1, 100) <= 15;
            $concessionId = null;
            $concessionAmount = 0;
            $concessionNote = null;

            if ($hasConcession) {
                $c = $concessionsToAssign[array_rand($concessionsToAssign)];
                $concessionId = DB::table('fee_concessions')->insertGetId([
                    'school_id'        => $schoolId,
                    'academic_year_id' => $academicYear->id,
                    'student_id'       => $student->id,
                    'name'             => $c['name'],
                    'type'             => $c['type'],
                    'value'            => $c['value'],
                    'is_active'        => true,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);

                $concessionAmount = $c['type'] === 'percentage'
                    ? 6000.00 * ($c['value'] / 100)
                    : $c['value'];
                $concessionNote = $c['name'];
            }

            $dueAmount  = 6000.00;
            $discount   = $concessionAmount;
            $netPayable = $dueAmount - $discount;

            $base = [
                'school_id'        => $schoolId,
                'student_id'       => $student->id,
                'academic_year_id' => $academicYear->id,
                'fee_head_id'      => $tuitionHead,
                'amount_due'       => $dueAmount,
                'discount'         => $discount,
                'concession_id'    => $concessionId,
                'concession_note'  => $concessionNote,
                'fine'             => 0,
                'collected_by'     => 1,
            ];

            if ($paymentScenario <= 6) {
                // Good Payer — pays Installment 1 and 2 fully and on time
                FeePayment::create(array_merge($base, [
                    'amount_paid'  => $netPayable,
                    'term'         => 'Installment 1',
                    'payment_date' => Carbon::parse($academicYear->start_date)->addDays(rand(1, 15)),
                    'payment_mode' => 'online',
                    'status'       => 'paid',
                ]));

                FeePayment::create(array_merge($base, [
                    'amount_paid'  => $netPayable,
                    'term'         => 'Installment 2',
                    'payment_date' => Carbon::parse($academicYear->start_date)->addMonths(4)->addDays(rand(1, 15)),
                    'payment_mode' => 'online',
                    'status'       => 'paid',
                ]));
            } elseif ($paymentScenario <= 8) {
                // Partial Payer — Installment 1 full, Installment 2 half
                FeePayment::create(array_merge($base, [
                    'amount_paid'  => $netPayable,
                    'term'         => 'Installment 1',
                    'payment_date' => Carbon::parse($academicYear->start_date)->addDays(rand(1, 20)),
                    'payment_mode' => 'cash',
                    'status'       => 'paid',
                ]));

                FeePayment::create(array_merge($base, [
                    'amount_paid'  => round($netPayable / 2, 2),
                    'term'         => 'Installment 2',
                    'payment_date' => Carbon::parse($academicYear->start_date)->addMonths(4)->addDays(rand(10, 25)),
                    'payment_mode' => 'cash',
                    'status'       => 'partial',
                ]));
            } elseif ($paymentScenario === 9) {
                // Late Payer — Installment 1 paid after due date with fine
                FeePayment::create(array_merge($base, [
                    'amount_paid'  => $netPayable + 200.00,
                    'fine'         => 200.00,
                    'term'         => 'Installment 1',
                    'payment_date' => Carbon::parse($academicYear->start_date)->addMonths(1)->addDays(rand(1, 15)),
                    'payment_mode' => 'cheque',
                    'status'       => 'paid',
                ]));
            }
            // Scenario 10: Defaulter — no payment rows (shows up in Due Report)
        }

        $this->command->info('✅ Dummy Fee Data (Groups, Heads, Structures, Payments) seeded successfully!');
    }
}
