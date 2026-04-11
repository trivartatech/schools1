<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. learning_materials — add missing section_id FK (if not already present) + soft deletes
        Schema::table('learning_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('learning_materials', 'section_id')) {
                $table->foreignId('section_id')->nullable()->after('class_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('learning_materials', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 2. online_classes — add recording_link + soft deletes
        Schema::table('online_classes', function (Blueprint $table) {
            if (!Schema::hasColumn('online_classes', 'recording_link')) {
                $table->string('recording_link')->nullable()->after('platform');
            }
            if (!Schema::hasColumn('online_classes', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 3. assignments — add status enum + soft deletes
        Schema::table('assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('assignments', 'status')) {
                $table->enum('status', ['draft', 'published', 'closed'])->default('published')->after('max_marks');
            }
            if (!Schema::hasColumn('assignments', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 4. syllabus_topics — add soft deletes
        Schema::table('syllabus_topics', function (Blueprint $table) {
            if (!Schema::hasColumn('syllabus_topics', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 5. student_diaries — add soft deletes
        Schema::table('student_diaries', function (Blueprint $table) {
            if (!Schema::hasColumn('student_diaries', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 6. book_lists — add soft deletes
        Schema::table('book_lists', function (Blueprint $table) {
            if (!Schema::hasColumn('book_lists', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('book_lists', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('student_diaries', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('syllabus_topics', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropSoftDeletes();
        });
        Schema::table('online_classes', function (Blueprint $table) {
            $table->dropColumn('recording_link');
            $table->dropSoftDeletes();
        });
        Schema::table('learning_materials', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
            $table->dropSoftDeletes();
        });
    }
};
