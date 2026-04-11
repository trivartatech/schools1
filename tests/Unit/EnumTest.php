<?php

namespace Tests\Unit;

use App\Enums\AttendanceStatus;
use App\Enums\FeePaymentStatus;
use App\Enums\GatePassStatus;
use App\Enums\LeaveStatus;
use App\Enums\PaymentMode;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use Tests\TestCase;

class EnumTest extends TestCase
{
    // ── UserType ──────────────────────────────────────────────────────────────

    public function test_user_type_from_string(): void
    {
        $this->assertEquals(UserType::Admin, UserType::from('admin'));
        $this->assertEquals(UserType::Teacher, UserType::from('teacher'));
        $this->assertEquals(UserType::SuperAdmin, UserType::from('super_admin'));
    }

    public function test_user_type_management_types(): void
    {
        $management = UserType::managementTypes();
        $this->assertContains('admin', $management);
        $this->assertContains('school_admin', $management);
        $this->assertContains('principal', $management);
        $this->assertNotContains('teacher', $management);
        $this->assertNotContains('student', $management);
    }

    public function test_user_type_is_management(): void
    {
        $this->assertTrue(UserType::Admin->isManagement());
        $this->assertTrue(UserType::Principal->isManagement());
        $this->assertFalse(UserType::Teacher->isManagement());
        $this->assertFalse(UserType::Student->isManagement());
    }

    public function test_user_type_staff_types(): void
    {
        $staffTypes = UserType::staffTypes();
        $this->assertContains('teacher', $staffTypes);
        $this->assertContains('accountant', $staffTypes);
        $this->assertNotContains('student', $staffTypes);
        $this->assertNotContains('parent', $staffTypes);
        $this->assertNotContains('driver', $staffTypes);
    }

    public function test_user_type_labels(): void
    {
        $this->assertEquals('Super Admin', UserType::SuperAdmin->label());
        $this->assertEquals('Teacher', UserType::Teacher->label());
        $this->assertEquals('Student', UserType::Student->label());
    }

    // ── StudentStatus ─────────────────────────────────────────────────────────

    public function test_student_status_values(): void
    {
        $this->assertEquals('active', StudentStatus::Active->value);
        $this->assertEquals('transferred', StudentStatus::Transferred->value);
    }

    public function test_student_status_colors(): void
    {
        $this->assertEquals('green', StudentStatus::Active->color());
        $this->assertEquals('red', StudentStatus::Suspended->color());
    }

    // ── FeePaymentStatus ──────────────────────────────────────────────────────

    public function test_fee_payment_status_values(): void
    {
        $this->assertEquals('paid', FeePaymentStatus::Paid->value);
        $this->assertEquals('partial', FeePaymentStatus::Partial->value);
        $this->assertEquals('unpaid', FeePaymentStatus::Unpaid->value);
        $this->assertEquals('waived', FeePaymentStatus::Waived->value);
    }

    public function test_fee_payment_status_colors(): void
    {
        $this->assertEquals('green', FeePaymentStatus::Paid->color());
        $this->assertEquals('red', FeePaymentStatus::Unpaid->color());
        $this->assertEquals('yellow', FeePaymentStatus::Partial->color());
    }

    // ── PaymentMode ───────────────────────────────────────────────────────────

    public function test_payment_mode_values(): void
    {
        $this->assertEquals('cash', PaymentMode::Cash->value);
        $this->assertEquals('online', PaymentMode::Online->value);
        $this->assertEquals('upi', PaymentMode::Upi->value);
    }

    // ── AttendanceStatus ──────────────────────────────────────────────────────

    public function test_attendance_counts_as_present(): void
    {
        $this->assertTrue(AttendanceStatus::Present->countsAsPresent());
        $this->assertTrue(AttendanceStatus::Late->countsAsPresent());
        $this->assertTrue(AttendanceStatus::HalfDay->countsAsPresent());
        $this->assertFalse(AttendanceStatus::Absent->countsAsPresent());
        $this->assertFalse(AttendanceStatus::Holiday->countsAsPresent());
        $this->assertFalse(AttendanceStatus::Leave->countsAsPresent());
    }

    // ── LeaveStatus ───────────────────────────────────────────────────────────

    public function test_leave_status_values(): void
    {
        $this->assertEquals('pending', LeaveStatus::Pending->value);
        $this->assertEquals('approved', LeaveStatus::Approved->value);
        $this->assertEquals('rejected', LeaveStatus::Rejected->value);
        $this->assertEquals('cancelled', LeaveStatus::Cancelled->value);
    }

    // ── GatePassStatus ────────────────────────────────────────────────────────

    public function test_gate_pass_status_values(): void
    {
        $this->assertEquals('pending', GatePassStatus::Pending->value);
        $this->assertEquals('approved', GatePassStatus::Approved->value);
        $this->assertEquals('exited', GatePassStatus::Exited->value);
    }

    // ── StaffStatus ───────────────────────────────────────────────────────────

    public function test_staff_status_values(): void
    {
        $this->assertEquals('active', StaffStatus::Active->value);
        $this->assertEquals('on_leave', StaffStatus::OnLeave->value);
        $this->assertEquals('terminated', StaffStatus::Terminated->value);
    }
}
