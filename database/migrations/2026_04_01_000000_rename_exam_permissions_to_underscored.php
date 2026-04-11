<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Permission::where('name', 'manage exam_terms')->update(['name' => 'manage_exam_terms']);
        Permission::where('name', 'manage exam_types')->update(['name' => 'manage_exam_types']);
        Permission::where('name', 'manage exam_schedules')->update(['name' => 'manage_exam_schedules']);
        Permission::where('name', 'manage exam_grades')->update(['name' => 'manage_exam_grades']);
        Permission::where('name', 'manage exam_assessments')->update(['name' => 'manage_exam_assessments']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'manage_exam_terms')->update(['name' => 'manage exam_terms']);
        Permission::where('name', 'manage_exam_types')->update(['name' => 'manage exam_types']);
        Permission::where('name', 'manage_exam_schedules')->update(['name' => 'manage exam_schedules']);
        Permission::where('name', 'manage_exam_grades')->update(['name' => 'manage exam_grades']);
        Permission::where('name', 'manage_exam_assessments')->update(['name' => 'manage exam_assessments']);
    }
};
