<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'manage exam_terms', 'module' => 'Examinations'],
            ['name' => 'manage exam_types', 'module' => 'Examinations'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p['name'], 'guard_name' => 'web'], ['module' => $p['module']]);
        }

        // Assign to Super Admin and School Admin by default
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo(['manage exam_terms', 'manage exam_types']);
        }
        
        $schoolAdmin = Role::where('name', 'School Admin')->first();
        if ($schoolAdmin) {
            $schoolAdmin->givePermissionTo(['manage exam_terms', 'manage exam_types']);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['manage exam_terms', 'manage exam_types'])->delete();
    }
};
