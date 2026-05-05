<?php

namespace App\Services\School;

use App\Models\School;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use App\Models\School\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    // Platform-level permissions that aren't grantable from a school context.
    // Submitting them returns an error rather than silently stripping.
    private const SYSTEM_PERMISSIONS = [
        'access_super_admin_panel',
        'impersonate_users',
    ];

    // Hard-locked roles. super_admin is platform-only; admin/school_admin can be
    // edited but with a self-lockout guard (see roleWiseAssign).
    private const LOCKED_ROLES = ['super_admin'];

    // Permissions that cannot be removed from admin-tier roles, because
    // doing so would lock the editing user out of this very page.
    private const SELF_LOCKOUT_GUARD = ['manage_roles'];

    private const ADMIN_TIER_ROLES = ['admin', 'school_admin', 'principal'];

    public function roleWiseAssign(Request $request, School $school): void
    {
        $roleId = $request->input('role_id');
        $permissionNames = $request->input('permissions', []);

        $role = SpatieRole::where('id', $roleId)->firstOrFail();

        if (in_array($role->name, self::LOCKED_ROLES, true)) {
            throw new \Exception('System core role "' . $role->name . '" cannot be modified.');
        }

        // Role must be either a global seeded role (school_id = null) or one
        // owned by this school. Roles from other schools are off-limits.
        if ($role->school_id !== null && $role->school_id !== $school->id) {
            throw new \Exception('You cannot modify roles owned by another school.');
        }

        // Reject system-only permissions outright instead of silently stripping
        // them — UI should never have offered these checkboxes in the first place.
        $rejected = array_intersect($permissionNames, self::SYSTEM_PERMISSIONS);
        if (!empty($rejected)) {
            throw new \Exception(
                'These platform-only permissions cannot be assigned from school context: '
                . implode(', ', $rejected)
            );
        }

        // Self-lockout guard: removing manage_roles from an admin-tier role would
        // strip the editing user's own access to this page on next request.
        if (in_array($role->name, self::ADMIN_TIER_ROLES, true)) {
            $missingGuards = array_diff(self::SELF_LOCKOUT_GUARD, $permissionNames);
            if (!empty($missingGuards)) {
                throw new \Exception(
                    'Cannot remove ' . implode(', ', $missingGuards)
                    . ' from "' . $role->name . '" — that would lock you out of role management.'
                );
            }
        }

        $role->syncPermissions($permissionNames);
    }

    public function userWiseAssign(Request $request, School $school): void
    {
        $userIds = $request->input('users', []);
        $permissionNames = $request->input('permissions', []);
        $action = $request->input('action', 'assign'); // 'assign' or 'revoke'

        $rejected = array_intersect($permissionNames, self::SYSTEM_PERMISSIONS);
        if (!empty($rejected)) {
            throw new \Exception(
                'These platform-only permissions cannot be assigned from school context: '
                . implode(', ', $rejected)
            );
        }

        // Only target users in this school
        $users = User::whereIn('id', $userIds)->where('school_id', $school->id)->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

        foreach ($users as $user) {
            if ($action === 'assign') {
                $user->givePermissionTo($permissionNames);
            } else {
                $user->revokePermissionTo($permissionNames);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Build a permission matrix for the roles-permissions UI.
     *
     * Returns:
     * [
     *   'modules' => [
     *     'Students' => [
     *       ['name' => 'view_students',   'label' => 'View',   'roles' => ['teacher' => true, 'accountant' => false, ...]],
     *       ['name' => 'create_students', 'label' => 'Create', 'roles' => [...]],
     *       ...
     *     ],
     *     'Finance & Fees' => [...],
     *     ...
     *   ],
     *   'roles' => ['teacher', 'accountant', 'principal', ...],  // ordered list
     * ]
     *
     * @param  string[]|null  $roleNames  Limit to these roles (default: all non-system roles)
     */
    public function getMatrix(?array $roleNames = null): array
    {
        $defaultRoles = ['principal', 'teacher', 'accountant', 'hr', 'receptionist', 'driver',
                         'hostel_warden', 'transport_manager', 'librarian', 'nurse', 'auditor'];

        $targetRoles = $roleNames ?? $defaultRoles;

        // Load roles with their permissions in one query each
        $roles = SpatieRole::whereIn('name', $targetRoles)
            ->with('permissions')
            ->get()
            ->keyBy('name');

        // All non-system permissions, grouped by their module label
        $allPermissions = SpatiePermission::whereNotIn('name', self::SYSTEM_PERMISSIONS)
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        // Friendly labels for well-known permission suffixes
        $labelMap = [
            'view'                  => 'View',
            'create'                => 'Create',
            'edit'                  => 'Edit',
            'delete'                => 'Delete',
            'analyse'               => 'Analyse',
            'export'                => 'Export',
            'bulk_import'           => 'Bulk Import',
            'promote'               => 'Promote',
            'view_documents'        => 'View Documents',
            'request_edit'          => 'Request Edit',
            'waive'                 => 'Waive Fee',
            'generate_receipt'      => 'Generate Receipt',
            'override_discount'     => 'Override Discount',
            'manage_structure'      => 'Manage Structure',
            'enter_marks'           => 'Enter Marks',
            'publish_results'       => 'Publish Results',
            'edit_past'             => 'Edit Past Records',
            'mark_scanner'          => 'QR Scanner',
            'approve'               => 'Approve',
            'apply'                 => 'Apply',
            'download_document'     => 'Download Document',
            'send_bulk_sms'         => 'Send Bulk SMS',
            'send_bulk_email'       => 'Send Bulk Email',
            'view_salary'           => 'View Salary',
            'download_payslip'      => 'Download Payslip',
            'view_financial'        => 'Financial Reports',
            'view_audit'            => 'Audit Log',
            'view_transport'        => 'Transport Access',
            'view_own'              => 'Own Data',
        ];

        $modules = [];

        foreach ($allPermissions as $permission) {
            $module = $permission->module ?? 'Other';

            // Derive a human-readable label from the permission name
            $label = $this->permissionLabel($permission->name, $labelMap);

            $roleFlags = [];
            foreach ($targetRoles as $roleName) {
                $roleFlags[$roleName] = isset($roles[$roleName])
                    && $roles[$roleName]->permissions->contains('name', $permission->name);
            }

            $modules[$module][] = [
                'name'  => $permission->name,
                'label' => $label,
                'roles' => $roleFlags,
            ];
        }

        // Sort modules alphabetically
        ksort($modules);

        return [
            'modules' => $modules,
            'roles'   => $targetRoles,
        ];
    }

    /**
     * Derive a human-readable label for a permission name.
     * Uses the labelMap for known suffix patterns, falls back to title-casing the name.
     */
    private function permissionLabel(string $name, array $labelMap): string
    {
        // Try longest-matching prefix from label map
        foreach ($labelMap as $fragment => $label) {
            if (str_contains($name, $fragment)) {
                return $label;
            }
        }

        // Fallback: replace underscores with spaces + ucwords
        return ucwords(str_replace('_', ' ', $name));
    }
}
