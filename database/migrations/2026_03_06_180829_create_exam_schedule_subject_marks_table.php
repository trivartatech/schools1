<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_schedule_subject_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_subject_id')->constrained('exam_schedule_subjects')->cascadeOnDelete();
            $table->foreignId('exam_assessment_item_id')->constrained('exam_assessment_items')->cascadeOnDelete();
            $table->decimal('max_marks', 6, 2);
            $table->decimal('passing_marks', 6, 2);
            $table->timestamps();

            $table->unique(['exam_schedule_subject_id', 'exam_assessment_item_id'], 'essm_unique_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedule_subject_marks');
    }
};
