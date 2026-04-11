<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedule_subjects', function (Blueprint $table) {
            $table->dropColumn(['writing_marks', 'passing_marks']);
            $table->foreignId('exam_assessment_id')->nullable()->after('is_enabled')
                  ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedule_subjects', function (Blueprint $table) {
            $table->dropForeign(['exam_assessment_id']);
            $table->dropColumn('exam_assessment_id');
            $table->decimal('writing_marks', 6, 2)->nullable();
            $table->decimal('passing_marks', 6, 2)->nullable();
        });
    }
};
