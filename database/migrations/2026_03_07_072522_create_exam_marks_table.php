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
        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_schedule_subject_id')->constrained()->cascadeOnDelete(); // links back to ExamScheduleSubject
            $table->foreignId('exam_assessment_item_id')->constrained()->cascadeOnDelete(); // links back to ExamAssessmentItem (e.g. Theory, Practical)
            
            $table->decimal('marks_obtained', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('teacher_remarks')->nullable();
            
            $table->timestamps();

            // Prevent duplicate entries for the same student, same subject, same assessment component
            $table->unique(['student_id', 'exam_schedule_subject_id', 'exam_assessment_item_id'], 'exam_marks_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_marks');
    }
};
