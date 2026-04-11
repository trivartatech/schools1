<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow diary entries without a subject (notice/reminder type)
        if (Schema::hasTable('student_diaries') && Schema::hasColumn('student_diaries', 'subject_id')) {
            Schema::table('student_diaries', function (Blueprint $table) {
                $table->foreignId('subject_id')->nullable()->change();
            });
        }

        // Allow learning materials without a file (external URL only)
        if (Schema::hasTable('learning_materials') && Schema::hasColumn('learning_materials', 'file_path')) {
            Schema::table('learning_materials', function (Blueprint $table) {
                $table->string('file_path')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Reverting nullable is risky if data already has nulls, so no-op
    }
};
