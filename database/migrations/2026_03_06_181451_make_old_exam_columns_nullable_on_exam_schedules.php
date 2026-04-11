<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_id')->nullable()->change();
            $table->unsignedBigInteger('section_id')->nullable()->change();
            $table->unsignedBigInteger('subject_id')->nullable()->change();
            $table->unsignedBigInteger('exam_assessment_id')->nullable()->change();
            $table->date('exam_date')->nullable()->change();
            $table->time('start_time')->nullable()->change();
            $table->integer('duration_minutes')->nullable()->change();
            $table->decimal('max_marks', 8, 2)->nullable()->change();
            $table->decimal('passing_marks', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Reverting this might cause data loss or constraint issues if data was inserted via new flow
    }
};
