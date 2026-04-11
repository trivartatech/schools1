<?php

namespace Tests\Feature;

use App\Enums\FeePaymentStatus;
use App\Enums\PaymentMode;
use App\Enums\UserType;
use App\Models\AcademicYear;
use App\Models\CourseClass;
use App\Models\Department;
use App\Models\FeeGroup;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FeePaymentTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private AcademicYear $academicYear;
    private User $accountant;
    private Student $student;
    private FeeHead $feeHead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name'     => 'Test School',
            'slug'     => 'test-school',
            'is_active'=> true,
        ]);

        $this->academicYear = AcademicYear::create([
            'school_id'  => $this->school->id,
            'name'       => '2025-26',
            'start_date' => '2025-04-01',
            'end_date'   => '2026-03-31',
            'is_current' => true,
        ]);

        $this->accountant = User::create([
            'name'      => 'Test Accountant',
            'email'     => 'accountant@test.com',
            'password'  => Hash::make('password'),
            'user_type' => UserType::Accountant->value,
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);

        $department = Department::create(['school_id' => $this->school->id, 'name' => 'Primary']);
        $class = CourseClass::create(['school_id' => $this->school->id, 'department_id' => $department->id, 'name' => 'Class 1']);
        $section = Section::create(['school_id' => $this->school->id, 'course_class_id' => $class->id, 'name' => 'A']);

        $studentUser = User::create([
            'name'      => 'Test Student',
            'email'     => 'student@test.com',
            'password'  => Hash::make('password'),
            'user_type' => UserType::Student->value,
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'school_id'      => $this->school->id,
            'user_id'        => $studentUser->id,
            'first_name'     => 'Test',
            'last_name'      => 'Student',
            'gender'         => 'Male',
            'admission_date' => now(),
            'status'         => 'active',
        ]);

        StudentAcademicHistory::create([
            'student_id'       => $this->student->id,
            'school_id'        => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'class_id'         => $class->id,
            'section_id'       => $section->id,
            'status'           => 'current',
        ]);

        $feeGroup = FeeGroup::create([
            'school_id' => $this->school->id,
            'name'      => 'Tuition Fees',
        ]);

        $this->feeHead = FeeHead::create([
            'school_id'   => $this->school->id,
            'fee_group_id'=> $feeGroup->id,
            'name'        => 'Monthly Fee',
            'amount'      => 5000,
        ]);

        // Bind school context (mimics ResolveTenant middleware)
        app()->bind('current_school_id', fn() => $this->school->id);
        app()->bind('current_academic_year_id', fn() => $this->academicYear->id);
        app()->bind('current_school', fn() => $this->school);
    }

    // ── Fee Payment Model ─────────────────────────────────────────────────────

    public function test_fee_payment_auto_generates_receipt_number(): void
    {
        $payment = FeePayment::create([
            'school_id'        => $this->school->id,
            'student_id'       => $this->student->id,
            'fee_head_id'      => $this->feeHead->id,
            'academic_year_id' => $this->academicYear->id,
            'amount_due'       => 5000,
            'amount_paid'      => 5000,
            'discount'         => 0,
            'fine'             => 0,
            'payment_mode'     => PaymentMode::Cash->value,
            'payment_date'     => now()->format('Y-m-d'),
            'status'           => FeePaymentStatus::Paid->value,
            'collected_by'     => $this->accountant->id,
        ]);

        $this->assertNotNull($payment->receipt_no);
        $this->assertStringContainsString('FEE-', $payment->receipt_no);
    }

    public function test_fee_payment_balance_is_computed_on_create(): void
    {
        $payment = FeePayment::create([
            'school_id'        => $this->school->id,
            'student_id'       => $this->student->id,
            'fee_head_id'      => $this->feeHead->id,
            'academic_year_id' => $this->academicYear->id,
            'amount_due'       => 5000,
            'amount_paid'      => 3000,
            'discount'         => 0,
            'fine'             => 0,
            'payment_mode'     => PaymentMode::Cash->value,
            'payment_date'     => now()->format('Y-m-d'),
            'status'           => FeePaymentStatus::Partial->value,
            'collected_by'     => $this->accountant->id,
        ]);

        // Balance = Due - Discount + Fine - Paid = 5000 - 0 + 0 - 3000 = 2000
        $this->assertEquals(2000, $payment->balance);
    }

    public function test_fee_payment_balance_is_zero_when_fully_paid(): void
    {
        $payment = FeePayment::create([
            'school_id'        => $this->school->id,
            'student_id'       => $this->student->id,
            'fee_head_id'      => $this->feeHead->id,
            'academic_year_id' => $this->academicYear->id,
            'amount_due'       => 5000,
            'amount_paid'      => 5000,
            'discount'         => 0,
            'fine'             => 0,
            'payment_mode'     => PaymentMode::Cash->value,
            'payment_date'     => now()->format('Y-m-d'),
            'status'           => FeePaymentStatus::Paid->value,
            'collected_by'     => $this->accountant->id,
        ]);

        $this->assertEquals(0, $payment->balance);
    }

    public function test_fee_payment_balance_accounts_for_discount(): void
    {
        $payment = FeePayment::create([
            'school_id'        => $this->school->id,
            'student_id'       => $this->student->id,
            'fee_head_id'      => $this->feeHead->id,
            'academic_year_id' => $this->academicYear->id,
            'amount_due'       => 5000,
            'amount_paid'      => 4500,
            'discount'         => 500, // concession
            'fine'             => 0,
            'payment_mode'     => PaymentMode::Cash->value,
            'payment_date'     => now()->format('Y-m-d'),
            'status'           => FeePaymentStatus::Paid->value,
            'collected_by'     => $this->accountant->id,
        ]);

        // Balance = 5000 - 500 + 0 - 4500 = 0
        $this->assertEquals(0, $payment->balance);
    }

    public function test_fee_payment_receipt_numbers_are_unique_per_school(): void
    {
        $pay1 = FeePayment::create([
            'school_id'        => $this->school->id,
            'student_id'       => $this->student->id,
            'fee_head_id'      => $this->feeHead->id,
            'academic_year_id' => $this->academicYear->id,
            'amount_due'       => 1000,
            'amount_paid'      => 1000,
            'discount'         => 0,
            'fine'             => 0,
            'payment_mode'     => PaymentMode::Cash->value,
            'payment_date'     => now()->format('Y-m-d'),
            'status'           => FeePaymentStatus::Paid->value,
            'collected_by'     => $this->accountant->id,
        ]);

        $pay2 = FeePayment::create([
            'school_id'        => $this->school->id,
            'student_id'       => $this->student->id,
            'fee_head_id'      => $this->feeHead->id,
            'academic_year_id' => $this->academicYear->id,
            'amount_due'       => 2000,
            'amount_paid'      => 2000,
            'discount'         => 0,
            'fine'             => 0,
            'payment_mode'     => PaymentMode::Cash->value,
            'payment_date'     => now()->format('Y-m-d'),
            'status'           => FeePaymentStatus::Paid->value,
            'collected_by'     => $this->accountant->id,
        ]);

        $this->assertNotEquals($pay1->receipt_no, $pay2->receipt_no);
    }

    public function test_fee_payment_can_be_soft_deleted(): void
    {
        $payment = FeePayment::create([
            'school_id'        => $this->school->id,
            'student_id'       => $this->student->id,
            'fee_head_id'      => $this->feeHead->id,
            'academic_year_id' => $this->academicYear->id,
            'amount_due'       => 1000,
            'amount_paid'      => 1000,
            'discount'         => 0,
            'fine'             => 0,
            'payment_mode'     => PaymentMode::Cash->value,
            'payment_date'     => now()->format('Y-m-d'),
            'status'           => FeePaymentStatus::Paid->value,
            'collected_by'     => $this->accountant->id,
        ]);

        $id = $payment->id;
        $payment->delete();

        $this->assertNull(FeePayment::find($id));
        $this->assertNotNull(FeePayment::withTrashed()->find($id));
    }
}
