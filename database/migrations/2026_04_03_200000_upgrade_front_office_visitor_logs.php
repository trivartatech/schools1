<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_logs', function (Blueprint $table) {
            $table->boolean('is_pre_registered')->default(false)->after('notes');
            $table->date('expected_date')->nullable()->after('is_pre_registered');
            $table->string('expected_time', 10)->nullable()->after('expected_date');
            $table->foreignId('pre_registered_by')->nullable()->after('expected_time')
                  ->constrained('users')->nullOnDelete();
            $table->string('badge_number', 20)->nullable()->after('pre_registered_by');
            $table->string('id_type', 50)->nullable()->after('badge_number');
            $table->string('id_number', 100)->nullable()->after('id_type');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_logs', function (Blueprint $table) {
            $table->dropForeign(['pre_registered_by']);
            $table->dropColumn([
                'is_pre_registered', 'expected_date', 'expected_time',
                'pre_registered_by', 'badge_number', 'id_type', 'id_number',
            ]);
        });
    }
};
