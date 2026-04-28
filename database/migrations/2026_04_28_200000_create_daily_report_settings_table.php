<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_report_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();

            // sections to include — JSON array of slugs
            $table->json('sections_enabled')->nullable();

            // thresholds
            $table->decimal('oversized_expense_threshold', 12, 2)->default(50000);
            $table->unsignedTinyInteger('low_attendance_threshold_pct')->default(70);
            $table->unsignedTinyInteger('repeat_absent_days')->default(3);

            // schedule
            $table->time('auto_send_time')->default('19:00:00');
            $table->boolean('auto_send_enabled')->default(true);
            $table->boolean('weekly_digest_enabled')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_settings');
    }
};
