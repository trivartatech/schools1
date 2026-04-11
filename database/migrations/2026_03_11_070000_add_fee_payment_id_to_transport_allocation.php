<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add fee_payment_id link to transport_student_allocation
        Schema::table('transport_student_allocation', function (Blueprint $table) {
            $table->foreignId('fee_payment_id')
                  ->nullable()
                  ->after('transport_fee')
                  ->constrained('fee_payments')
                  ->nullOnDelete();
        });

        // 2. Ensure "TRANSPORT" fee head doesn't require a fee_group_id
        //    (make fee_group_id nullable on fee_heads if it isn't already)
        if (Schema::hasColumn('fee_heads', 'fee_group_id')) {
            Schema::table('fee_heads', function (Blueprint $table) {
                $table->foreignId('fee_group_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('transport_student_allocation', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_payment_id');
        });
    }
};
