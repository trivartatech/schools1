<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Announcement;
use App\Models\FeeGroup;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use App\Policies\FeeGroupPolicy;
use App\Policies\LeavePolicy;
use App\Policies\PayrollPolicy;
use App\Policies\StaffPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private School $otherSchool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name'      => 'Test School',
            'slug'      => 'test-school',
            'is_active' => true,
        ]);

        $this->otherSchool = School::create([
            'name'      => 'Other School',
            'slug'      => 'other-school',
            'is_active' => true,
        ]);

        // Seed the minimal permissions needed for policy checks.
        $perms = [
            'view_staff', 'create_staff', 'edit_staff', 'delete_staff',
            'edit_payroll', 'view_payroll', 'create_payroll', 'delete_payroll',
            'view_communication', 'create_communication', 'edit_communication', 'delete_communication',
            'view_fee', 'create_fee', 'edit_fee', 'delete_fee',
        ];
        foreach ($perms as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Scope Spatie to this school's team.
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(UserType $type, ?School $school = null): User
    {
        static $n = 0;
        $n++;
        return User::create([
            'name'      => "User {$n}",
            'email'     => "user{$n}@test.com",
            'password'  => Hash::make('secret'),
            'user_type' => $type->value,
            'school_id' => ($school ?? $this->school)->id,
            'is_active' => true,
        ]);
    }

    private function grantPermission(User $user, string ...$permissions): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->school_id);
        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ── StaffPolicy ───────────────────────────────────────────────────────────

    public function test_staff_self_view_requires_no_permission(): void
    {
        $user  = $this->makeUser(UserType::Teacher);
        $staff = Staff::create(['school_id' => $this->school->id, 'user_id' => $user->id]);

        $this->assertTrue((new StaffPolicy)->view($user, $staff));
    }

    public function test_staff_view_own_returns_true_even_without_view_staff(): void
    {
        $user  = $this->makeUser(UserType::Teacher);
        $staff = Staff::create(['school_id' => $this->school->id, 'user_id' => $user->id]);

        // Teacher has no permissions — but it's their own profile.
        $this->assertFalse($user->hasPermissionTo('view_staff'));
        $this->assertTrue((new StaffPolicy)->view($user, $staff));
    }

    public function test_staff_view_other_requires_view_staff_permission(): void
    {
        $manager      = $this->makeUser(UserType::Admin);
        $otherStaff   = Staff::create(['school_id' => $this->school->id]);

        // Without permission: denied.
        $this->assertFalse((new StaffPolicy)->view($manager, $otherStaff));

        // With permission: allowed.
        $this->grantPermission($manager, 'view_staff');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $manager->refresh();
        $this->assertTrue((new StaffPolicy)->view($manager, $otherStaff));
    }

    public function test_staff_view_blocked_for_different_school(): void
    {
        $manager = $this->makeUser(UserType::Admin);
        $this->grantPermission($manager, 'view_staff');

        // Staff record belongs to the other school.
        $crossSchoolStaff = Staff::create(['school_id' => $this->otherSchool->id]);

        $this->assertFalse((new StaffPolicy)->view($manager, $crossSchoolStaff));
    }

    public function test_staff_viewAny_requires_view_staff(): void
    {
        $teacher = $this->makeUser(UserType::Teacher);
        $this->assertFalse((new StaffPolicy)->viewAny($teacher));

        $hr = $this->makeUser(UserType::Admin);
        $this->grantPermission($hr, 'view_staff');
        $hr->refresh();
        $this->assertTrue((new StaffPolicy)->viewAny($hr));
    }

    public function test_staff_create_requires_create_staff(): void
    {
        $user = $this->makeUser(UserType::Admin);
        $this->assertFalse((new StaffPolicy)->create($user));

        $this->grantPermission($user, 'create_staff');
        $user->refresh();
        $this->assertTrue((new StaffPolicy)->create($user));
    }

    // ── LeavePolicy ───────────────────────────────────────────────────────────

    public function test_leave_create_allowed_for_any_user(): void
    {
        $teacher = $this->makeUser(UserType::Teacher);
        $this->assertTrue((new LeavePolicy)->create($teacher));
    }

    public function test_leave_view_own_leave_without_permission(): void
    {
        $user  = $this->makeUser(UserType::Teacher);
        $leave = Leave::create([
            'school_id'  => $this->school->id,
            'user_id'    => $user->id,
            'leave_type' => 'sick',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->toDateString(),
            'reason'     => 'Test',
            'status'     => 'pending',
        ]);

        $this->assertTrue((new LeavePolicy)->view($user, $leave));
    }

    public function test_leave_delete_own_pending_allowed(): void
    {
        $user  = $this->makeUser(UserType::Teacher);
        $leave = Leave::create([
            'school_id'  => $this->school->id,
            'user_id'    => $user->id,
            'leave_type' => 'casual',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->toDateString(),
            'reason'     => 'Personal',
            'status'     => 'pending',
        ]);

        $this->assertTrue((new LeavePolicy)->delete($user, $leave));
    }

    public function test_leave_delete_approved_leave_denied_for_owner(): void
    {
        $user  = $this->makeUser(UserType::Teacher);
        $leave = Leave::create([
            'school_id'  => $this->school->id,
            'user_id'    => $user->id,
            'leave_type' => 'casual',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->toDateString(),
            'reason'     => 'Personal',
            'status'     => 'approved',
        ]);

        $this->assertFalse((new LeavePolicy)->delete($user, $leave));
    }

    public function test_leave_update_requires_edit_staff(): void
    {
        $hr    = $this->makeUser(UserType::Admin);
        $leave = Leave::create([
            'school_id'  => $this->school->id,
            'user_id'    => $this->makeUser(UserType::Teacher)->id,
            'leave_type' => 'casual',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->toDateString(),
            'reason'     => 'Test',
            'status'     => 'pending',
        ]);

        $this->assertFalse((new LeavePolicy)->update($hr, $leave));

        $this->grantPermission($hr, 'edit_staff');
        $hr->refresh();
        $this->assertTrue((new LeavePolicy)->update($hr, $leave));
    }

    // ── PayrollPolicy ─────────────────────────────────────────────────────────

    public function test_payroll_viewAny_requires_view_payroll(): void
    {
        $teacher = $this->makeUser(UserType::Teacher);
        $this->assertFalse((new PayrollPolicy)->viewAny($teacher));

        $accountant = $this->makeUser(UserType::Accountant);
        $this->grantPermission($accountant, 'view_payroll');
        $accountant->refresh();
        $this->assertTrue((new PayrollPolicy)->viewAny($accountant));
    }

    public function test_payroll_create_requires_create_payroll(): void
    {
        $user = $this->makeUser(UserType::Admin);
        $this->assertFalse((new PayrollPolicy)->create($user));

        $this->grantPermission($user, 'create_payroll');
        $user->refresh();
        $this->assertTrue((new PayrollPolicy)->create($user));
    }

    // ── AnnouncementPolicy ────────────────────────────────────────────────────

    public function test_announcement_viewAny_requires_view_communication(): void
    {
        $student = $this->makeUser(UserType::Student);
        $this->assertFalse((new AnnouncementPolicy)->viewAny($student));

        $teacher = $this->makeUser(UserType::Teacher);
        $this->grantPermission($teacher, 'view_communication');
        $teacher->refresh();
        $this->assertTrue((new AnnouncementPolicy)->viewAny($teacher));
    }

    public function test_announcement_create_requires_create_communication(): void
    {
        $teacher = $this->makeUser(UserType::Teacher);
        $this->assertFalse((new AnnouncementPolicy)->create($teacher));

        $this->grantPermission($teacher, 'create_communication');
        $teacher->refresh();
        $this->assertTrue((new AnnouncementPolicy)->create($teacher));
    }

    public function test_announcement_view_blocked_for_different_school(): void
    {
        $user  = $this->makeUser(UserType::Teacher);
        $this->grantPermission($user, 'view_communication');
        $user->refresh();

        $announcement = Announcement::create([
            'school_id'       => $this->otherSchool->id,
            'sender_id'       => $user->id,
            'title'           => 'Test',
            'delivery_method' => 'sms',
            'audience_type'   => 'school',
        ]);

        $this->assertFalse((new AnnouncementPolicy)->view($user, $announcement));
    }

    // ── FeeGroupPolicy ────────────────────────────────────────────────────────

    public function test_fee_group_viewAny_requires_view_fee(): void
    {
        $teacher = $this->makeUser(UserType::Teacher);
        $this->assertFalse((new FeeGroupPolicy)->viewAny($teacher));

        $accountant = $this->makeUser(UserType::Accountant);
        $this->grantPermission($accountant, 'view_fee');
        $accountant->refresh();
        $this->assertTrue((new FeeGroupPolicy)->viewAny($accountant));
    }

    public function test_fee_group_update_blocked_for_different_school(): void
    {
        $user = $this->makeUser(UserType::Admin);
        $this->grantPermission($user, 'edit_fee');
        $user->refresh();

        $feeGroup = FeeGroup::create([
            'school_id' => $this->otherSchool->id,
            'name'      => 'Tuition',
        ]);

        $this->assertFalse((new FeeGroupPolicy)->update($user, $feeGroup));
    }

    public function test_fee_group_update_allowed_same_school(): void
    {
        $user = $this->makeUser(UserType::Admin);
        $this->grantPermission($user, 'edit_fee');
        $user->refresh();

        $feeGroup = FeeGroup::create([
            'school_id' => $this->school->id,
            'name'      => 'Tuition',
        ]);

        $this->assertTrue((new FeeGroupPolicy)->update($user, $feeGroup));
    }
}
