<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Students should be able to view their own stationary kit allocation —
        // the parent role already has these permissions; the student role was missing them.
        $student = Role::where('name', 'student')->where('guard_name', 'web')->first();
        if ($student) {
            $student->givePermissionTo([
                'view_stationary',
                'view_stationary_allocations',
            ]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $student = Role::where('name', 'student')->where('guard_name', 'web')->first();
        if ($student) {
            $student->revokePermissionTo([
                'view_stationary',
                'view_stationary_allocations',
            ]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
