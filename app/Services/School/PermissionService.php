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
    // Permissions that cannot be assigned at school level (system-only)
    private const SYSTEM_PERMISSIONS = [
        'manage_roles',
        'access_super_admin_panel',
        'impersonate_users',
    ];

    // Roles that school admins are not allowed to modify
    private const LOCKED_ROLES = ['super_admin', 'admin'];

    public function roleWiseAssign(Request $request, School $school): void
    {
        $roleId = $request->input('role_id');
        $permissionNames = $request->input('permissions', []);

        $role = SpatieRole::where('id', $roleId)->firstOrFail();

        if (in_array($role->name, self::LOCKED_ROLES)) {
            throw new \Exception('System core roles cannot be modified.');
        }

        // Role must belong to this school (null school_id = global, not editable by school admin)
        if ($role->school_id !== $school->id) {
            throw new \Exception('You do not have permission to modify this role context.');
        }

        // Strip system permissions before applying
        $safeNames = array_diff($permissionNames, self::SYSTEM_PERMISSIONS);
        $role->syncPermissions($safeNames);
    }

    public function userWiseAssign(Request $request, School $school): void
    {
        $userIds = $request->input('users', []);
        $permissionNames = $request->input('permissions', []);
        $action = $request->input('action', 'assign'); // 'assign' or 'revoke'

        $safeNames = array_diff($permissionNames, self::SYSTEM_PERMISSIONS);

        // Only target users in this school
        $users = User::whereIn('id', $userIds)->where('school_id', $school->id)->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

        foreach ($users as $user) {
            if ($action === 'assign') {
                $user->givePermissionTo($safeNames);
            } else {
                $user->revokePermissionTo($safeNames);
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
