<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'manage exam_grades', 'module' => 'Examinations'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p['name'], 'guard_name' => 'web'], ['module' => $p['module']]);
        }

        $rolesToAssign = ['Super Admin', 'School Admin', 'principal'];
        
        foreach ($rolesToAssign as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo('manage exam_grades');
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['manage exam_grades'])->delete();
    }
};
