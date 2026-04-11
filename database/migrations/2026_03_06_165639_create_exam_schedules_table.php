<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('exam_assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('exam_date')->nullable();
            $table->time('start_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->decimal('max_marks', 8, 2)->nullable();
            $table->decimal('passing_marks', 8, 2)->nullable();
            $table->boolean('is_co_scholastic')->default(false);
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_class_id')->constrained()->cascadeOnDelete();
            $table->boolean('has_co_scholastic')->default(false);
            $table->foreignId('scholastic_grading_system_id')->nullable()->constrained('grading_systems')->nullOnDelete();
            $table->foreignId('co_scholastic_grading_system_id')->nullable()->constrained('grading_systems')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
    }
};
