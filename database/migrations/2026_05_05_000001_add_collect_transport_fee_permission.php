<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Clear the Spatie cache so the new permission is picked up immediately
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create the permission (idempotent — safe to run even if it already exists)
        Permission::firstOrCreate(
            ['name' => 'collect_transport_fee', 'guard_name' => 'web'],
            ['module' => 'Transport']
        );

        // Assign to every role that should be able to record transport fee payments
        $roles = [
            'super_admin',
            'admin',
            'school_admin',
            'principal',
            'accountant',
            'transport_manager',
        ];

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo('collect_transport_fee');
            }
        }

        // Clear the cache again after assigning
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'collect_transport_fee')
                  ->where('guard_name', 'web')
                  ->delete();
    }
};
