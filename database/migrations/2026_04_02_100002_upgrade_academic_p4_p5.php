<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // P4-A: Diary read receipts — track which parents/students have seen each entry
        if (!Schema::hasTable('diary_reads')) {
            Schema::create('diary_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('diary_id')->constrained('student_diaries')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('read_at');
                $table->timestamps();

                $table->unique(['diary_id', 'user_id']);
                $table->index('diary_id');
            });
        }

        // P4-B: Diary homework completion — students mark a diary entry's homework as done
        if (!Schema::hasTable('diary_completions')) {
            Schema::create('diary_completions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('diary_id')->constrained('student_diaries')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->timestamp('completed_at');
                $table->timestamps();

                $table->unique(['diary_id', 'student_id']);
                $table->index('diary_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_completions');
        Schema::dropIfExists('diary_reads');
    }
};
