import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { SIDEBAR_MENU } from '@/config/sidebar.js';

/**
 * usePermissions — Role & permission helpers for Vue components.
 *
 * Sourced from $page.props.auth (injected by HandleInertiaRequests).
 * All permission checks are driven by the Spatie permission array (auth.permissions),
 * NOT by role name — ensuring the backend is the single source of truth.
 *
 * ─── Roles ────────────────────────────────────────────────────────────────────
 *   super_admin  Platform super user
 *   admin        School administrator  (formerly school_admin + principal)
 *   teacher      Class / subject teacher
 *   student      Enrolled student
 *   parent       Guardian / parent
 *   accountant   Fee & finance officer
 *   driver       Transport driver
 *
 * ─── Usage ────────────────────────────────────────────────────────────────────
 *   const { can, canDo, hasRole, isAdmin, isTeacher, canAccess } = usePermissions();
 *
 *   // Exact Spatie permission:
 *   v-if="can('view_attendance')"
 *
 *   // CRUD shorthand — canDo(action, module):
 *   v-if="canDo('view',   'students')"
 *   v-if="canDo('create', 'students')"   // alias: 'add'
 *   v-if="canDo('edit',   'students')"   // alias: 'update'
 *   v-if="canDo('delete', 'students')"   // alias: 'remove'
 *
 *   // Student-module permission flags (requirement-driven):
 *   v-if="student.view"       → canDo('view', 'students')
 *   v-if="student.edit"       → canDo('edit', 'students')
 *   v-if="student.create"     → canDo('create', 'students')
 *   v-if="student.analyse"    → can('analyse_students')
 *
 *   // Role checks (use only for UI hints — never for access control):
 *   v-if="hasRole('admin')"
 *   v-if="hasAnyRole(['admin', 'super_admin'])"
 *
 *   // Sidebar nav gates:
 *   v-if="canAccess.finance.value"
 */
export function usePermissions() {
    const page = usePage();

    const auth        = computed(() => page.props.auth ?? {});
    const userType    = computed(() => auth.value.user?.user_type ?? null);
    const roles       = computed(() => auth.value.roles ?? []);
    const permissions = computed(() => auth.value.permissions ?? []);

    // ── Permission helpers ───────────────────────────────────────────────

    /**
     * Check an exact Spatie permission name.
     * Source of truth: auth.permissions[] (backend-injected array).
     * Example: can('view_students')
     */
    const can = (permission) => permissions.value.includes(permission);

    /**
     * CRUD shorthand — resolves aliases, builds '{verb}_{module}'.
     * Aliases:  read→view | add→create | update→edit | remove→delete | analyse→analyse
     * Example:  canDo('view', 'students')  →  can('view_students')
     */
    const canDo = (action, module) => {
        const alias = {
            read:    'view',
            add:     'create',
            update:  'edit',
            remove:  'delete',
            analyse: 'analyse',
        };
        const verb = alias[action] ?? action;
        return permissions.value.includes(`${verb}_${module}`);
    };

    // ── Role helpers ─────────────────────────────────────────────────────
    // These are purely for UI conveniences (e.g. show/hide a sidebar group).
    // Never use these alone for access-control — always pair with a can() check.

    const hasRole    = (role)     => roles.value.includes(role);
    const hasAnyRole = (roleList) => roleList.some(r => roles.value.includes(r));
    const isType     = (type)     => userType.value === type;

    /** Check if user has AT LEAST ONE of the given permissions */
    const canAny = (permissionList) => permissionList.some(p => permissions.value.includes(p));

    /** Check if user has ALL of the given permissions */
    const canAll = (permissionList) => permissionList.every(p => permissions.value.includes(p));

    // ── Convenience role flags ────────────────────────────────────────────
    const isSuperAdmin  = computed(() => isType('super_admin'));
    const isAdmin       = computed(() => isType('admin') || isType('school_admin') || isType('principal'));
    const isTeacher     = computed(() => isType('teacher'));
    const isStudent     = computed(() => isType('student'));
    const isParent      = computed(() => isType('parent'));
    const isAccountant  = computed(() => isType('accountant'));
    const isDriver      = computed(() => isType('driver'));

    /** Admin OR Super Admin */
    const isSchoolManagement = computed(() => isAdmin.value || isSuperAdmin.value);

    // ── Sidebar nav-section gates ─────────────────────────────────────────
    // These use Spatie can() checks so they automatically reflect DB permission changes.
    const canAccess = {
        // Management sections — permission-backed
        academicStructure: computed(() => can('view_classes')),
        academicResources: computed(() => can('view_academic')),
        studentManagement: computed(() => can('view_students') && (isSchoolManagement.value || isTeacher.value)),
        attendance:        computed(() => can('view_attendance')),
        examinations:      computed(() => can('view_exam')),
        schedule:          computed(() => can('view_schedule')),
        finance:           computed(() => can('view_fee')),
        hr:                computed(() => can('view_hr')),
        transport:         computed(() => canAny(['view_transport_vehicles', 'view_transport_routes', 'view_transport_allocations', 'view_transport_tracking'])),
        communication:     computed(() => can('view_communication')),
        settings:          computed(() => can('view_settings')),
        hostel:            computed(() => can('view_hostel')),
        frontOffice:       computed(() => can('view_front_office')),
        chat:              computed(() => can('view_chat')),

        // Portal gate — parent/student self-service
        portal:            computed(() => can('view_portal') || isParent.value || isStudent.value),
    };

    // ════════════════════════════════════════════════════════════════════
    // Student module permission flags — driven by Spatie permissions
    // (Requirement: parent must have view=true, edit=false, create=false)
    //
    // These map directly to Spatie permission names and are the SINGLE
    // source of truth for any component that gates student data access.
    //
    //   student.view     → can('view_students')      parent: ✅ true
    //   student.edit     → can('edit_students')      parent: ❌ false
    //   student.create   → can('create_students')    parent: ❌ false
    //   student.delete   → can('delete_students')    parent: ❌ false
    //   student.analyse  → can('analyse_students')   parent: ✅ optional (seeded true)
    // ════════════════════════════════════════════════════════════════════

    /** student.view = true for parent, teacher, admin, accountant, student */
    const canViewStudent    = computed(() => can('view_students'));

    /** student.edit = false for parent. True only for admin/teacher */
    const canEditStudent    = computed(() => can('edit_students'));

    /** student.create = false for parent. True only for admin */
    const canCreateStudent  = computed(() => can('create_students'));

    /** student.delete = false for parent. True only for admin */
    const canDeleteStudent  = computed(() => can('delete_students'));

    /**
     * student.analyse = optional for parent.
     * Checks 'analyse_students' Spatie permission.
     * Falls back to view_reports for admin/teacher who have report access.
     */
    const canAnalyseStudent = computed(() => can('analyse_students') || can('view_reports'));

    // ── Portal self-service flags (parent/student) ────────────────────────
    // These use 'view_own_*' permissions which are scoped to the user's own data.
    const canViewOwnStudent    = computed(() => can('view_own_student'));
    const canViewOwnAttendance = computed(() => can('view_own_attendance'));
    const canViewOwnFee        = computed(() => can('view_own_fee'));
    const canViewOwnExam       = computed(() => can('view_own_exam'));
    const canRequestEditStudent = computed(() => can('request_edit_students') || can('edit_students'));
    const canRequestEditStaff   = computed(() => can('request_edit_staff') || can('edit_staff'));

    // ── Student sub-module granular flags ────────────────────────────────────
    /** Bulk import students from CSV/Excel — admin/principal only */
    const canBulkImportStudents  = computed(() => can('bulk_import_students'));
    /** Promote students to next class/year in bulk */
    const canPromoteStudents     = computed(() => can('promote_students'));
    /** View student documents (certificates, TC, birth cert) */
    const canViewStudentDocuments = computed(() => can('view_student_documents'));

    // ── Attendance sub-module granular flags ─────────────────────────────────
    /** Correct historical attendance records (admin/principal) */
    const canEditPastAttendance  = computed(() => can('edit_past_attendance'));
    /** Export attendance report */
    const canExportAttendance    = computed(() => can('export_attendance'));

    // ── Exam sub-module granular flags ───────────────────────────────────────
    /** Enter exam marks (teacher action) */
    const canEnterExamMarks      = computed(() => can('enter_exam_marks'));
    /** Publish exam results to student/parent portal (principal/admin) */
    const canPublishExamResults  = computed(() => can('publish_exam_results'));

    // ── Fee & Finance sub-module granular flags ──────────────────────────────
    /** Waive outstanding fee — accountant/admin with explicit permission */
    const canWaiveFee            = computed(() => can('waive_fee'));
    /** Generate/print a fee receipt PDF */
    const canGenerateFeeReceipt  = computed(() => can('generate_fee_receipt'));
    /** Apply manual discount override — admin only */
    const canOverrideFeeDiscount = computed(() => can('override_fee_discount'));
    /** Manage fee structure (fee heads, groups, types) */
    const canManageFeeStructure  = computed(() => can('manage_fee_structure'));
    /** View detailed financial reports (P&L, balance summary) */
    const canViewFinancialReports = computed(() => can('view_financial_reports') || can('view_reports'));

    // ── Staff & HR sub-module granular flags ─────────────────────────────────
    /** View detailed salary breakdown for staff */
    const canViewStaffSalary     = computed(() => can('view_staff_salary'));
    /** Approve staff leave requests */
    const canApproveLeave        = computed(() => can('approve_leave'));
    /** Download own payslip */
    const canDownloadPayslip     = computed(() => can('download_payslip'));

    // ── Communication sub-module granular flags ──────────────────────────────
    /** Send bulk SMS to parents/staff/students */
    const canSendBulkSms         = computed(() => can('send_bulk_sms'));
    /** Send bulk email to parents/staff/students */
    const canSendBulkEmail       = computed(() => can('send_bulk_email'));

    // ── System flags ─────────────────────────────────────────────────────────
    /** View audit log — hr/admin/auditor */
    const canViewAuditLog        = computed(() => can('view_audit_log'));

    // ── Sidebar filter utility ────────────────────────────────────────────────
    /**
     * Filters the SIDEBAR_MENU config down to only items the current user
     * can see. Uses permission checks + onlyFor/excludeFor user_type gates.
     *
     * Returns a new array (does not mutate the source config).
     */
    const filteredSidebarMenu = computed(() => {
        const type = userType.value;

        const itemVisible = (item) => {
            // alwaysShow: bypasses permission checks
            if (item.alwaysShow) return true;
            // permissions array (any-match)
            if (item.permissions && item.permissions.length > 0) return canAny(item.permissions);
            // single permission
            if (item.permission) return can(item.permission);
            // no permission key → always show
            return true;
        };

        return SIDEBAR_MENU
            .map(item => {
                if (!item.children) return itemVisible(item) ? item : null;
                // Group: filter children, then check if any remain
                const visibleChildren = item.children.filter(c => {
                    if (!c.permission) return true;
                    return can(c.permission);
                });
                if (!itemVisible(item) || visibleChildren.length === 0) return null;
                return { ...item, children: visibleChildren };
            })
            .filter(Boolean);
    });

    return {
        // raw data
        userType, roles, permissions,

        // helpers
        can, canDo, canAny, canAll, hasRole, hasAnyRole, isType,

        // role flags (UI hints only — not for access control)
        isSuperAdmin, isAdmin, isTeacher, isStudent,
        isParent, isAccountant, isDriver,
        isSchoolManagement,

        // nav gates (permission-backed)
        canAccess,

        // ── Dynamic sidebar menu (filtered by permissions + user_type) ──
        filteredSidebarMenu,

        // ── Student module RBAC flags ──
        canViewStudent,        // student.view
        canEditStudent,        // student.edit
        canCreateStudent,      // student.create
        canDeleteStudent,      // student.delete
        canAnalyseStudent,     // student.analyse (optional)

        // ── Portal self-service flags ──
        canViewOwnStudent,
        canViewOwnAttendance,
        canViewOwnFee,
        canViewOwnExam,
        canRequestEditStudent,
        canRequestEditStaff,

        // ── Student sub-module granular flags ──
        canBulkImportStudents,
        canPromoteStudents,
        canViewStudentDocuments,

        // ── Attendance granular flags ──
        canEditPastAttendance,
        canExportAttendance,

        // ── Exam granular flags ──
        canEnterExamMarks,
        canPublishExamResults,

        // ── Fee & Finance granular flags ──
        canWaiveFee,
        canGenerateFeeReceipt,
        canOverrideFeeDiscount,
        canManageFeeStructure,
        canViewFinancialReports,

        // ── Staff & HR granular flags ──
        canViewStaffSalary,
        canApproveLeave,
        canDownloadPayslip,

        // ── Communication granular flags ──
        canSendBulkSms,
        canSendBulkEmail,

        // ── System flags ──
        canViewAuditLog,
    };
}
