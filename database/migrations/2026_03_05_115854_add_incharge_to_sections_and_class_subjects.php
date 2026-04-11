<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add class_incharge_staff_id to course_classes
        Schema::table('course_classes', function (Blueprint $table) {
            $table->foreignId('incharge_staff_id')->nullable()->constrained('staff')->nullOnDelete()->after('name');
        });

        // Add section_incharge_staff_id to sections
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('incharge_staff_id')->nullable()->constrained('staff')->nullOnDelete()->after('name');
        });

        // Add subject_incharge_staff_id to class_subjects
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->foreignId('incharge_staff_id')->nullable()->constrained('staff')->nullOnDelete()->after('subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->dropForeign(['incharge_staff_id']);
            $table->dropColumn('incharge_staff_id');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeign(['incharge_staff_id']);
            $table->dropColumn('incharge_staff_id');
        });

        Schema::table('course_classes', function (Blueprint $table) {
            $table->dropForeign(['incharge_staff_id']);
            $table->dropColumn('incharge_staff_id');
        });
    }
};
