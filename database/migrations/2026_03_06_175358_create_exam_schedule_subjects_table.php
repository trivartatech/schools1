<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_schedule_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_co_scholastic')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->decimal('writing_marks', 6, 2)->nullable();
            $table->decimal('passing_marks', 6, 2)->nullable();
            $table->date('exam_date')->nullable();
            $table->time('exam_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamps();

            $table->unique(['exam_schedule_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedule_subjects');
    }
};
