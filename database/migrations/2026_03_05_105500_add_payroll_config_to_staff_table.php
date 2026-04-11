<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // Store individualized payroll rules as JSON
            // e.g. {"DA": {"type": "percent", "value": 52}, "HRA": {"type": "percent", "value": 24}, "TA": {"type": "fixed", "value": 1600}}
            $table->json('allowances_config')->nullable()->after('basic_salary');
            
            // e.g. {"PF": {"type": "percent", "value": 12}, "ESI": {"type": "percent", "value": 0.75}}
            $table->json('deductions_config')->nullable()->after('allowances_config');
            
            // Store exact TDS amount or percentage if applicable
            $table->json('tax_config')->nullable()->after('deductions_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['allowances_config', 'deductions_config', 'tax_config']);
        });
    }
};
