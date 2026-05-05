<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create the four CRUD library permissions (idempotent)
        $permissions = [
            'view_library',
            'create_library',
            'edit_library',
            'delete_library',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['module' => 'Library']
            );
        }

        // Roles that get FULL library access (all 4 permissions)
        $fullAccess = ['super_admin', 'admin', 'school_admin', 'principal', 'librarian'];

        foreach ($fullAccess as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        // Roles that get VIEW-ONLY library access
        $viewOnly = ['teacher', 'student', 'parent'];

        foreach ($viewOnly as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo('view_library');
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'view_library', 'create_library', 'edit_library', 'delete_library',
        ])->where('guard_name', 'web')->delete();
    }
};
