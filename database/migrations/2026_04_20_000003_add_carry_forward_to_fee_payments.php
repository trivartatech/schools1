<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->boolean('is_carry_forward')->default(false)->after('status');
            $table->foreignId('source_payment_id')->nullable()->after('is_carry_forward')
                ->constrained('fee_payments')->nullOnDelete();
            $table->foreignId('source_year_id')->nullable()->after('source_payment_id')
                ->constrained('academic_years')->nullOnDelete();
            $table->foreignId('rollover_run_id')->nullable()->after('source_year_id')
                ->constrained('rollover_runs')->nullOnDelete();

            $table->index(['student_id', 'is_carry_forward']);
        });
    }

    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropForeign(['source_payment_id']);
            $table->dropForeign(['source_year_id']);
            $table->dropForeign(['rollover_run_id']);
            $table->dropIndex(['student_id', 'is_carry_forward']);
            $table->dropColumn(['is_carry_forward', 'source_payment_id', 'source_year_id', 'rollover_run_id']);
        });
    }
};
