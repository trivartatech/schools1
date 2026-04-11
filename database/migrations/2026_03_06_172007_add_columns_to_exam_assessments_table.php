<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_assessments', function (Blueprint $table) {
            // Add missing description column
            if (!Schema::hasColumn('exam_assessments', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });

        Schema::table('exam_assessment_items', function (Blueprint $table) {
            // Add missing sort_order + timestamps if not present
            if (!Schema::hasColumn('exam_assessment_items', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('code');
            }
            if (!Schema::hasColumn('exam_assessment_items', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_assessments', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
