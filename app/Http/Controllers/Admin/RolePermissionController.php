<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\School\PermissionService;
use App\Services\School\RoleService;

class RolePermissionController extends Controller
{
    /**
     * Display the Role & Permission Matrix.
     */
    public function index(PermissionService $service)
    {
        $schoolId = app()->bound('current_school')
            ? app('current_school')->id
            : request()->user()?->school_id;

        // All roles visible in this school context (exclude super_admin from matrix)
        $roles = Role::where(function ($query) use ($schoolId) {
            $query->whereNull('school_id')
                  ->orWhere('school_id', $schoolId);
        })->where('name', '!=', 'super_admin')->get();

        $permissionsQuery = Permission::query();
        if (!request()->user()?->isSuperAdmin()) {
            $permissionsQuery->whereNotIn('name', ['manage_roles', 'access_super_admin_panel', 'impersonate_users']);
        }
        $groupedPermissions = $permissionsQuery->get()->groupBy('module');

        // Raw join for the checkbox state in the matrix table
        $rolePermissions = DB::table('role_has_permissions')->get();

        // ── New: permission matrix keyed by role name (used by search/filter UI) ──
        $roleNames = $roles->pluck('name')->toArray();
        $matrix    = $service->getMatrix($roleNames);

        // ── New: school users for the User Overrides panel ──────────────────────
        $schoolUsers = $schoolId
            ? User::where('school_id', $schoolId)
                ->select('id', 'name', 'email', 'user_type')
                ->orderBy('name')
                ->get()
            : collect();

        // Direct (user-specific) permission overrides for every school user
        $userDirectPermissions = DB::table('model_has_permissions')
            ->where('model_type', 'App\\Models\\User')
            ->whereIn('model_id', $schoolUsers->pluck('id'))
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->select('model_id as user_id', 'permissions.name as permission')
            ->get()
            ->groupBy('user_id')
            ->map(fn($perms) => $perms->pluck('permission')->values());

        return Inertia::render('Admin/Roles/Matrix', [
            'roles'                  => $roles,
            'groupedPermissions'     => $groupedPermissions,
            'rolePermissions'        => $rolePermissions,
            'matrix'                 => $matrix,
            'schoolUsers'            => $schoolUsers,
            'userDirectPermissions'  => $userDirectPermissions,
        ]);
    }

    /**
     * Create a new role.
     */
    public function store(Request $request, RoleService $service)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',          // lowercase_snake_case only
            ],
            'label' => 'required|string|max:100', // Human-readable label
        ], [
            'name.regex' => 'Role name must be lowercase letters, numbers, and underscores only (e.g. class_teacher).',
        ]);

        $school = (app()->bound('current_school') ? app('current_school') : null) ?? request()->user()?->school;

        if (!$school) {
            return back()->with('error', "No active school context to attach the role.");
        }

        $service->create($request, $school);

        return back()->with('success', "Role \"{$validated['label']}\" created successfully for your school.");
    }

    /**
     * Delete a custom role.
     */
    public function destroy(\App\Models\School\Role $role, RoleService $service)
    {
        $school = (app()->bound('current_school') ? app('current_school') : null) ?? request()->user()?->school;

        try {
            $service->deletable($school, $role);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        // Revoke from all users first scoped by permissions override
        DB::table('model_has_roles')->where('role_id', $role->id)->delete();
        // Remove all permission assignments
        DB::table('role_has_permissions')->where('role_id', $role->id)->delete();

        $role->delete();

        return back()->with('success', "Role \"{$role->name}\" deleted successfully.");
    }

    /**
     * Update permissions for a specific role (Role-Wise Assignment replication).
     */
    public function update(Request $request, PermissionService $service)
    {
        $request->validate([
            'role_id'       => 'required|exists:roles,id',
            'permissions'   => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $school = (app()->bound('current_school') ? app('current_school') : null) ?? request()->user()?->school;

        if (!$school) {
            return back()->with('error', "No active school context.");
        }

        try {
            $service->roleWiseAssign($request, $school);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Permissions updated successfully.");
    }

    /**
     * Update permissions for specific users (User-Wise Assignment replication).
     */
    public function updateUserPermissions(Request $request, PermissionService $service)
    {
        $request->validate([
            'users'         => 'required|array',
            'users.*'       => 'exists:users,id',
            'permissions'   => 'array',
            'action'        => 'required|in:assign,revoke'
        ]);

        $school = (app()->bound('current_school') ? app('current_school') : null) ?? request()->user()?->school;

        try {
            $service->userWiseAssign($request, $school);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "User specific permissions updated.");
    }
}
