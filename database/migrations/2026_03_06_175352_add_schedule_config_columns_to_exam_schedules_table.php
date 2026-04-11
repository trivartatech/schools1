<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_schedules', 'school_id')) {
                $table->foreignId('school_id')->after('id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('exam_schedules', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->after('school_id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('exam_schedules', 'exam_type_id')) {
                $table->foreignId('exam_type_id')->after('academic_year_id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('exam_schedules', 'course_class_id')) {
                $table->foreignId('course_class_id')->after('exam_type_id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('exam_schedules', 'has_co_scholastic')) {
                $table->boolean('has_co_scholastic')->default(false)->after('course_class_id');
            }
            if (!Schema::hasColumn('exam_schedules', 'scholastic_grading_system_id')) {
                $table->foreignId('scholastic_grading_system_id')->nullable()->after('has_co_scholastic')
                    ->constrained('grading_systems')->nullOnDelete();
            }
            if (!Schema::hasColumn('exam_schedules', 'co_scholastic_grading_system_id')) {
                $table->foreignId('co_scholastic_grading_system_id')->nullable()->after('scholastic_grading_system_id')
                    ->constrained('grading_systems')->nullOnDelete();
            }
            if (!Schema::hasColumn('exam_schedules', 'status')) {
                $table->enum('status', ['draft', 'published'])->default('draft')->after('co_scholastic_grading_system_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropColumn(['has_co_scholastic', 'status']);
        });
    }
};
