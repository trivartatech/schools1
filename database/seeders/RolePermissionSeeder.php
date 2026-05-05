<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * RolePermissionSeeder
 * ─────────────────────────────────────────────────────────────────────────────
 * Creates every CRUD permission for every ERP module, then assigns
 * precisely-scoped permission sets to each role.
 *
 * Roles:
 *   super_admin  — Platform super-user (all permissions)
 *   admin        — School administrator (all school-level permissions)
 *   teacher      — Class/subject teacher
 *   student      — Enrolled student (portal view only)
 *   parent       — Guardian/parent (portal + student view)
 *   accountant   — Fee & finance officer
 *   driver       — Transport driver
 *
 * Run:  php artisan db:seed --class=RolePermissionSeeder
 */
class RolePermissionSeeder extends Seeder
{
    // Standard CRUD verbs
    private const ACTIONS = ['view', 'create', 'edit', 'delete'];

    /**
     * All ERP modules.
     * key   → used to build permission names: {action}_{key}
     * value → human-readable group label for the permission matrix UI
     */
    private const MODULES = [
        // ── Core School Management ──────────────────────────────────────
        'students'      => 'Students',
        'staff'         => 'Staff & HR',
        'attendance'    => 'Attendance',
        'academic'      => 'Academic Resources',
        'classes'       => 'Academic Structure',
        'exam'          => 'Examinations',
        'schedule'      => 'Timetable & Schedule',

        // ── Finance ─────────────────────────────────────────────────────
        'fee'           => 'Finance & Fees',
        'expense'       => 'Expenses',
        'payroll'       => 'Payroll',

        // ── Operations ──────────────────────────────────────────────────
        'transport_vehicles'    => 'Transport: Vehicles',
        'transport_routes'      => 'Transport: Routes & Stops',
        'transport_allocations' => 'Transport: Student Allocation',
        'transport_tracking'    => 'Transport: Tracking',
        'stationary_items'         => 'Stationary: Items',
        'stationary_allocations'   => 'Stationary: Student Allocation',
        'hostel'        => 'Hostel',
        'houses'        => 'Student Houses',
        'front_office'  => 'Front Office',
        'library'       => 'Library',

        // ── Communication ───────────────────────────────────────────────
        'communication' => 'Communication & Announcements',
        'chat'          => 'Chat',

        // ── Administration ───────────────────────────────────────────────
        'hr'            => 'HR Management',
        'settings'      => 'Settings & Setup',

        // ── Portal (parent/student self-service) ─────────────────────────
        'portal'        => 'Parent/Student Portal',

        // ── Student Leaves ───────────────────────────────────────────────
        'student_leaves' => 'Student Leaves',
    ];

    // ── Special (non-CRUD) permissions ──────────────────────────────────────
    private const SPECIAL_PERMISSIONS = [
        // ── System ───────────────────────────────────────────────────────────
        'manage_roles'              => 'System',
        'access_super_admin_panel'  => 'System',
        'impersonate_users'         => 'System',
        'view_audit_log'            => 'System',

        // ── Reporting ────────────────────────────────────────────────────────
        'view_reports'              => 'Finance & Fees',
        'view_financial_reports'    => 'Finance & Fees',  // P&L, balance summary
        'export_data'               => 'Students',

        // ── Student sub-module ────────────────────────────────────────────────
        // student.analyse = optional for parents (seeded as granted)
        'analyse_students'          => 'Students',
        'request_edit_students'     => 'Students',
        'bulk_import_students'      => 'Students',     // CSV/Excel bulk import
        'promote_students'          => 'Students',     // end-of-year class promotion
        'view_student_documents'    => 'Students',     // certificates, TC, birth cert

        // ── Staff & HR sub-module ─────────────────────────────────────────────
        'request_edit_staff'        => 'Staff & HR',
        'approve_leave'             => 'Staff & HR',   // approve staff leave requests
        'download_payslip'          => 'Staff & HR',   // staff: download own payslip
        'view_staff_salary'         => 'Staff & HR',   // detailed salary breakdown

        // ── Attendance sub-module ─────────────────────────────────────────────
        'mark_attendance_scanner'   => 'Attendance',   // QR scanner entry
        'edit_past_attendance'      => 'Attendance',   // correct historical records
        'export_attendance'         => 'Attendance',

        // ── Fee & Finance sub-module ──────────────────────────────────────────
        'waive_fee'                 => 'Finance & Fees',   // waive outstanding fees
        'generate_fee_receipt'      => 'Finance & Fees',   // print/PDF receipt
        'override_fee_discount'     => 'Finance & Fees',   // apply manual discount
        'manage_fee_structure'      => 'Finance & Fees',   // fee heads + groups config

        // ── Exam sub-module ───────────────────────────────────────────────────
        'enter_exam_marks'          => 'Examinations',     // teacher mark entry
        'publish_exam_results'      => 'Examinations',     // release results to portal
        'manage_exam_schedules'     => 'Examinations',
        'manage_exam_terms'         => 'Examinations',
        'manage_exam_types'         => 'Examinations',
        'manage_exam_grades'        => 'Examinations',
        'manage_exam_assessments'   => 'Examinations',

        // ── Communication sub-module ──────────────────────────────────────────
        'send_bulk_sms'             => 'Communication & Announcements',
        'send_bulk_email'           => 'Communication & Announcements',

        // ── Transport ────────────────────────────────────────────────────────
        'view_transport'            => 'Transport',
        'collect_transport_fee'     => 'Transport',   // record transport-fee receipts

        // ── Stationary ───────────────────────────────────────────────────────
        'view_stationary'           => 'Stationary',
        'collect_stationary_fee'    => 'Stationary',  // record stationary-fee receipts
        'issue_stationary_items'    => 'Stationary',  // hand items to students (qty_collected ↑)
        'accept_stationary_returns' => 'Stationary',  // accept returns + post refund GL

        // ── Hostel ───────────────────────────────────────────────────────────
        'collect_hostel_fee'        => 'Hostel',      // record hostel-fee receipts

        // ── Portal (parent/student self-service) ─────────────────────────────
        'view_own_student'          => 'Parent/Student Portal',
        'view_own_attendance'       => 'Parent/Student Portal',
        'view_own_fee'              => 'Parent/Student Portal',
        'view_own_exam'             => 'Parent/Student Portal',

        // ── Student Leave Actions ─────────────────────────────────────────────
        'approve_student_leaves'             => 'Student Leaves',
        'apply_student_leave'                => 'Student Leaves',
        'download_student_leave_document'    => 'Student Leaves',

        // ── Rollover (Academic Year Transition) ──────────────────────────────
        'manage_rollover'           => 'Settings & Setup',   // start/view rollover runs
        'execute_rollover'          => 'Settings & Setup',   // run structure + students + fees phases
        'finalize_rollover'         => 'Settings & Setup',   // finalize + freeze source year
        'rollback_rollover'         => 'Settings & Setup',   // reverse a run (future; wired now so roles align)
    ];

    public function run(): void
    {
        // Clear the Spatie permission cache first
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── 1. Create all CRUD permissions ──────────────────────────────────
        foreach (self::MODULES as $module => $label) {
            foreach (self::ACTIONS as $action) {
                $perm = Permission::findOrCreate("{$action}_{$module}", 'web');
                $perm->module = $label;
                $perm->save();
            }
        }

        // ── 2. Create special permissions ────────────────────────────────────
        foreach (self::SPECIAL_PERMISSIONS as $name => $group) {
            $perm = Permission::findOrCreate($name, 'web');
            $perm->module = $group;
            $perm->save();
        }

        // ── 3. Create roles ──────────────────────────────────────────────────
        $roleNames = [
            // Academic
            'teacher', 'student', 'parent',
            // Administration — each user_type now maps to its own role
            'super_admin', 'admin', 'school_admin', 'principal', 'hr',
            // Finance
            'accountant',
            // Operations
            'receptionist', 'front_office', 'front_gate_keeper',
            // Transport
            'driver', 'conductor', 'transport_manager',
            // Stationary
            'stationary_manager',
            // Facility
            'hostel_warden', 'mess_manager',
            // Service
            'librarian', 'nurse',
            // System
            'auditor', 'it_support',
        ];

        $roleLabels = [
            'super_admin'       => ['Super Admin',          'Platform superuser — unrestricted access to everything'],
            'admin'             => ['School Admin',         'Full access to all school-level features and settings'],
            'school_admin'      => ['School Admin (Alt)',   'School-level administrator with customisable permissions'],
            'principal'         => ['Principal',            'Academic head — manages curriculum, exams, and attendance'],
            'hr'                => ['HR Officer',           'Human resources — manages staff records, leave, and payroll'],
            'teacher'           => ['Teacher',              'Class/subject teacher — marks attendance, enters exam marks'],
            'accountant'        => ['Accountant',           'Finance officer — manages fees, expenses, and receipts'],
            'student'           => ['Student',              'Enrolled student — portal self-service access only'],
            'parent'            => ['Parent / Guardian',    "Guardian — views child's attendance, fees, and results"],
            'driver'            => ['Driver',               'Transport driver — views assigned route and vehicle'],
            'conductor'         => ['Conductor',            'Transport conductor — views route and tracking'],
            'transport_manager' => ['Transport Manager',    'Manages vehicles, routes, and student allocations'],
            'stationary_manager'=> ['Stationary Manager',   'Manages stationary items, allocations, issuances, returns, and fee collection'],
            'receptionist'      => ['Receptionist',         'Front office staff — manages visitors and enquiries'],
            'front_office'      => ['Front Office',         'Front office operations including enquiries and gate passes'],
            'front_gate_keeper' => ['Front Gate Keeper',    'School gate security — logs visitor entry/exit and verifies student identity'],
            'hostel_warden'     => ['Hostel Warden',        'Manages hostel rooms, students, and approvals'],
            'mess_manager'      => ['Mess Manager',         'Manages hostel mess menu and records'],
            'librarian'         => ['Librarian',            'Manages library resources and student book access'],
            'nurse'             => ['Nurse / Medical',      'School nurse — views student health records'],
            'auditor'           => ['Auditor',              'Read-only access to all modules for compliance auditing'],
            'it_support'        => ['IT Support',           'Technical support — troubleshoots system and user issues'],
        ];

        foreach ($roleNames as $name) {
            $role = Role::findOrCreate($name, 'web');
            if (isset($roleLabels[$name])) {
                [$label, $description] = $roleLabels[$name];
                $role->label       = $label;
                $role->description = $description;
                $role->save();
            }
        }

        // ── 4. Assign permissions per role ────────────────────────────────────

        // ── super_admin: ALL permissions ───────────────────────────────────
        Role::findByName('super_admin')->givePermissionTo(Permission::all());

        // ── admin: ALL permissions ─────────────────────────────────────────
        Role::findByName('admin')->givePermissionTo(Permission::all());

        // ── school_admin: ALL permissions ──────────────────────────────────
        Role::findByName('school_admin')->givePermissionTo(Permission::all());

        // ── principal: ALL permissions ─────────────────────────────────────
        Role::findByName('principal')->givePermissionTo(Permission::all());

        // ── teacher ────────────────────────────────────────────────────────
        // Teachers can: view students, manage attendance, manage academic
        // resources (diary/assignments/syllabus), view/create exams, view schedule
        Role::findByName('teacher')->syncPermissions([
            // Students — view + analyse + documents (teachers need to see student files)
            'view_students', 'analyse_students', 'view_student_documents',

            // Attendance — mark + view + edit + export (corrections within own class)
            'view_attendance', 'create_attendance', 'edit_attendance',
            'mark_attendance_scanner', 'export_attendance',

            // Academic resources — full CRUD (diary, assignments, syllabus, materials)
            'view_academic', 'create_academic', 'edit_academic', 'delete_academic',

            // Exams — view + enter marks (publishing is a principal action)
            'view_exam', 'create_exam', 'edit_exam',
            'enter_exam_marks',
            'manage_exam_schedules', 'manage_exam_terms', 'manage_exam_types',
            'manage_exam_grades', 'manage_exam_assessments',

            // Timetable — view only
            'view_schedule',

            // Chat
            'view_chat', 'create_chat',

            // Reports (their own class)
            'view_reports',

            // Own payslip download
            'download_payslip',

            // Transport — view
            'view_transport',

            // Library — view (teachers can browse the catalogue and check issue status)
            'view_library',

            // Student Leaves — teachers can view all, approve/reject, and download documents
            'view_student_leaves', 'approve_student_leaves', 'download_student_leave_document',
        ]);

        // ── student ────────────────────────────────────────────────────────
        // Students can only view their own portal data
        Role::findByName('student')->syncPermissions([
            'view_students',              // own profile
            'view_academic',              // assignments / diary / resources
            'view_exam',                  // exam schedules + own results
            'view_schedule',              // timetable
            'view_student_documents',     // own certificates / TC

            // Portal-specific self-service permissions
            'view_own_student',
            'view_own_attendance',
            'view_own_fee',
            'view_own_exam',

            // Generate own fee receipt
            'generate_fee_receipt',

            // Chat (can receive/send in their section group)
            'view_chat', 'create_chat',

            // Transport — view own bus/route
            'view_transport',

            // Stationary — view own kit allocation (mirrors what parent role has)
            'view_stationary',
            'view_stationary_allocations',

            // Student Leaves — students can view/apply for their own + download their own document
            'view_student_leaves', 'create_student_leaves', 'apply_student_leave',
            'download_student_leave_document',
        ]);

        // ── parent ─────────────────────────────────────────────────────────
        // Requirements:
        //   student.view     = true   → 'view_students'    ✅
        //   student.edit     = false  → 'edit_students'    ❌ NOT granted
        //   student.create   = false  → 'create_students'  ❌ NOT granted
        //   student.delete   = false  → 'delete_students'  ❌ NOT granted
        //   student.analyse  = optional → 'analyse_students' ✅ granted (optional = allowed)
        Role::findByName('parent')->syncPermissions([
            // Student — view + analyse + documents ONLY (edit/create/delete excluded)
            'view_students',
            'analyse_students',           // optional → granted
            'view_student_documents',     // view child's certificates

            // Attendance — view only
            'view_attendance',

            // Fees — view only + generate own receipt
            'view_fee',
            'generate_fee_receipt',

            // Exam results — view only
            'view_exam',

            // Portal self-service (own data)
            'view_portal',
            'view_own_student',
            'view_own_attendance',
            'view_own_fee',
            'view_own_exam',

            // Chat — parents can message teachers/admin
            'view_chat', 'create_chat',

            // Student Leaves — parents can view, apply for their child, and download documents
            'view_student_leaves', 'create_student_leaves', 'apply_student_leave',
            'download_student_leave_document',

            // Transport — view their child's bus/route
            'view_transport_vehicles',
            'view_transport_routes',
            'view_transport_allocations',
            'view_transport_tracking',
            'view_transport',

            // Hostel — view gate passes for their child
            'view_hostel',

            // Stationary — view their child's allocation
            'view_stationary',
            'view_stationary_allocations',
        ]);

        // ── accountant ──────────────────────────────────────────────────────
        // Full fee management + student view + expense management + payroll view
        Role::findByName('accountant')->syncPermissions([
            // Students — view only (need to select student for fee)
            'view_students',

            // Fee — full CRUD + granular fee actions
            'view_fee', 'create_fee', 'edit_fee', 'delete_fee',
            'waive_fee', 'generate_fee_receipt', 'manage_fee_structure',

            // Transport fee collection (standalone from Finance > Fee)
            'view_transport_allocations',
            'collect_transport_fee',

            // Hostel fee collection (standalone from Finance > Fee)
            'view_hostel',
            'collect_hostel_fee',

            // Stationary fee collection + returns (standalone from Finance > Fee)
            'view_stationary',
            'view_stationary_allocations',
            'collect_stationary_fee',
            'accept_stationary_returns',

            // Expense — full CRUD
            'view_expense', 'create_expense', 'edit_expense', 'delete_expense',

            // Payroll — view + payslip download
            'view_payroll', 'download_payslip',

            // Reports — including financial reports
            'view_reports', 'view_financial_reports', 'export_data',

            // Chat
            'view_chat', 'create_chat',
        ]);

        // ── driver ──────────────────────────────────────────────────────────
        // Drivers can view their route/vehicle assignment and chat
        Role::findByName('driver')->syncPermissions([
            'view_transport_vehicles',
            'view_transport_routes',
            'view_transport_tracking',
            'view_chat', 'create_chat',
            'view_transport',
        ]);

        // ── hr ──────────────────────────────────────────────────────────────
        Role::findByName('hr')->syncPermissions([
            // Staff — full CRUD
            'view_staff', 'create_staff', 'edit_staff', 'delete_staff',
            'view_staff_salary',

            // Payroll — full management
            'view_payroll', 'create_payroll', 'edit_payroll', 'delete_payroll',
            'download_payslip',

            // Leave — approve staff leave
            'approve_leave',
            'view_attendance',

            // Audit
            'view_audit_log',
        ]);

        // ── receptionist / front_office ──────────────────────────────────────
        $receptionPerms = [
            'view_front_office', 'create_front_office', 'edit_front_office', 'delete_front_office',
            'view_students', 'view_attendance',
        ];
        Role::findByName('receptionist')->syncPermissions($receptionPerms);
        Role::findByName('front_office')->syncPermissions($receptionPerms);

        // ── front_gate_keeper ────────────────────────────────────────────────
        // Gate security: logs visitor entry/exit, verifies student identity,
        // and can use the QR attendance scanner at the school gate.
        Role::findByName('front_gate_keeper')->syncPermissions([
            'view_front_office', 'create_front_office', 'edit_front_office',
            'view_students',
            'view_attendance', 'mark_attendance_scanner',
            'view_chat', 'create_chat',
        ]);

        // ── transport_manager ────────────────────────────────────────────────
        Role::findByName('transport_manager')->syncPermissions([
            'view_transport_vehicles', 'create_transport_vehicles', 'edit_transport_vehicles', 'delete_transport_vehicles',
            'view_transport_routes', 'create_transport_routes', 'edit_transport_routes', 'delete_transport_routes',
            'view_transport_allocations', 'create_transport_allocations', 'edit_transport_allocations', 'delete_transport_allocations',
            'view_transport_tracking',
            'collect_transport_fee',
            'generate_fee_receipt',
            'view_students',
            'view_transport',
        ]);

        // ── conductor ────────────────────────────────────────────────────────
        Role::findByName('conductor')->syncPermissions([
            'view_transport_vehicles',
            'view_transport_routes',
            'view_transport_tracking',
            'view_transport',
        ]);

        // ── stationary_manager ───────────────────────────────────────────────
        Role::findByName('stationary_manager')->syncPermissions([
            'view_stationary_items', 'create_stationary_items', 'edit_stationary_items', 'delete_stationary_items',
            'view_stationary_allocations', 'create_stationary_allocations', 'edit_stationary_allocations', 'delete_stationary_allocations',
            'collect_stationary_fee',
            'issue_stationary_items',
            'accept_stationary_returns',
            'generate_fee_receipt',
            'view_students',
            'view_stationary',
        ]);

        // ── hostel_warden ────────────────────────────────────────────────────
        Role::findByName('hostel_warden')->syncPermissions([
            'view_hostel', 'create_hostel', 'edit_hostel', 'delete_hostel',
            'collect_hostel_fee',
            'view_students',
        ]);

        // ── mess_manager ─────────────────────────────────────────────────────
        Role::findByName('mess_manager')->syncPermissions([
             'view_hostel', 'create_hostel',
        ]);

        // ── librarian ────────────────────────────────────────────────────────
        Role::findByName('librarian')->syncPermissions([
            'view_academic', 'view_students',
            'view_library', 'create_library', 'edit_library', 'delete_library',
        ]);

        // ── nurse ────────────────────────────────────────────────────────────
        Role::findByName('nurse')->syncPermissions([
            'view_students',
        ]);

        // ── auditor ──────────────────────────────────────────────────────────
        // Auditors have read access to everything + audit log
        Role::findByName('auditor')->syncPermissions(
            Permission::where(function ($q) {
                $q->where('name', 'like', 'view_%')
                  ->orWhereIn('name', ['export_data', 'view_audit_log', 'view_financial_reports', 'view_reports']);
            })->get()
        );

        // ── 5. Assign roles to existing users by user_type ──────────────────
        // This syncs any existing users in the DB with the correct Spatie role
        // so the permission system works immediately without re-logging-in.
        $this->assignRolesToExistingUsers();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ Roles and Permissions seeded successfully!');
        $this->command->table(
            ['Role', 'Permission Count'],
            collect($roleNames)->map(fn($r) => [
                $r,
                Role::findByName($r)->permissions()->count(),
            ])->toArray()
        );
    }

    /**
     * Sync Spatie roles to all existing users based on their user_type.
     * Safe to run multiple times (uses syncRoles).
     */
    private function assignRolesToExistingUsers(): void
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $count = 0;

        \Illuminate\Support\Facades\DB::table('users')->orderBy('id')->chunk(100, function ($rows) use (&$count, $registrar) {
            foreach ($rows as $row) {
                $typeEnum = \App\Enums\UserType::tryFrom((string) $row->user_type);
                $roleName = $typeEnum?->toSpatieRole();

                if ($roleName && Role::where('name', $roleName)->where('guard_name', 'web')->exists()) {
                    $user = \App\Models\User::withoutGlobalScopes()->find($row->id);
                    if ($user) {
                        // Set team context to match how the web app resolves it:
                        // school-scoped users → school_id as team; platform users → null
                        $teamId = $row->school_id ?? null;
                        $registrar->setPermissionsTeamId($teamId);
                        $registrar->forgetCachedPermissions();

                        $user->unsetRelation('permissions')->unsetRelation('roles');
                        $user->syncRoles([$roleName]);
                        $count++;
                    }
                }
            }
        });

        // Reset team context
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $this->command->info("  → Synced Spatie roles for {$count} existing users.");
    }
}
