<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Late submission tracking on assignment_submissions
        if (!Schema::hasColumn('assignment_submissions', 'is_late')) {
            Schema::table('assignment_submissions', function (Blueprint $table) {
                $table->boolean('is_late')->default(false)->after('submitted_at');
            });
        }

        // 2. External URL + published flag on learning_materials
        Schema::table('learning_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('learning_materials', 'external_url')) {
                $table->string('external_url', 500)->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('learning_materials', 'is_published')) {
                $table->boolean('is_published')->default(true)->after('external_url');
            }
            if (!Schema::hasColumn('learning_materials', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
        });

        // 3. Material download tracking
        if (!Schema::hasTable('material_downloads')) {
            Schema::create('material_downloads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_material_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->timestamp('downloaded_at');
                $table->timestamps();

                $table->index(['learning_material_id', 'student_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('material_downloads');

        Schema::table('learning_materials', function (Blueprint $table) {
            $table->dropColumnIfExists('external_url');
            $table->dropColumnIfExists('is_published');
            $table->dropColumnIfExists('description');
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumnIfExists('is_late');
        });
    }
};
