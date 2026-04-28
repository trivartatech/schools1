<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `student_type` to student_applications so the New Student Application
 * form can capture "New Student" / "Old Student" at submission time and
 * forward it to the academic-history row when the application is approved.
 *
 * Same column already exists on `student_academic_histories` and is what
 * the runtime fee resolver reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->string('student_type', 50)->nullable()->after('section_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->dropColumn('student_type');
        });
    }
};
