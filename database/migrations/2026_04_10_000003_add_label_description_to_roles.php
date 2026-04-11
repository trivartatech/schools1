<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds human-readable label and description columns to the roles table.
 *
 * - label       : Display name shown in the UI (e.g. "School Admin" instead of "school_admin")
 * - description : One-liner explaining the role's purpose for hover-tips in the Matrix UI
 *
 * Both are nullable so existing rows are unaffected; run RolePermissionSeeder to backfill them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('label', 100)->nullable()->after('name');
            $table->string('description', 255)->nullable()->after('label');
        });

        // Backfill labels for the known system roles so the UI is immediately usable
        // without having to re-run the full seeder.
        $labels = [
            'super_admin'       => ['Super Admin',          'Platform superuser — unrestricted access to everything'],
            'admin'             => ['School Admin',         'Full access to all school-level features and settings'],
            'school_admin'      => ['School Admin (Alt)',   'School-level administrator with customisable permissions'],
            'principal'         => ['Principal',            'Academic head — manages curriculum, exams, and attendance'],
            'teacher'           => ['Teacher',              'Class/subject teacher — marks attendance, enters exam marks'],
            'hr'                => ['HR Officer',           'Human resources — manages staff records, leave, and payroll'],
            'accountant'        => ['Accountant',           'Finance officer — manages fees, expenses, and receipts'],
            'student'           => ['Student',              'Enrolled student — portal self-service access only'],
            'parent'            => ['Parent / Guardian',    'Guardian — views child\'s attendance, fees, and results'],
            'driver'            => ['Driver',               'Transport driver — views assigned route and vehicle'],
            'conductor'         => ['Conductor',            'Transport conductor — views route and tracking'],
            'transport_manager' => ['Transport Manager',    'Manages vehicles, routes, and student allocations'],
            'receptionist'      => ['Receptionist',         'Front office staff — manages visitors and enquiries'],
            'front_office'      => ['Front Office',         'Front office operations including enquiries and gate passes'],
            'hostel_warden'     => ['Hostel Warden',        'Manages hostel rooms, students, and approvals'],
            'mess_manager'      => ['Mess Manager',         'Manages hostel mess menu and records'],
            'librarian'         => ['Librarian',            'Manages library resources and student book access'],
            'nurse'             => ['Nurse / Medical',      'School nurse — views student health records'],
            'auditor'           => ['Auditor',              'Read-only access to all modules for compliance auditing'],
        ];

        foreach ($labels as $name => [$label, $description]) {
            DB::table('roles')
                ->where('name', $name)
                ->whereNull('label')
                ->update(['label' => $label, 'description' => $description]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['label', 'description']);
        });
    }
};
