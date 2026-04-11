<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->integer('unpaid_leave_days')->default(0)->after('deductions');
            $table->decimal('unpaid_leave_deduction', 10, 2)->default(0)->after('unpaid_leave_days');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('unpaid_leave_days');
            $table->dropColumn('unpaid_leave_deduction');
        });
    }
};
