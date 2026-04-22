<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decouple the Transport fee flow from the Finance/Fee module.
 *
 *  1. Drop `is_transport_fee` from `fee_heads` — no longer used.
 *  2. Drop `fee_payment_id` FK from `transport_student_allocation` —
 *     collection is tracked natively on the allocation + a dedicated
 *     `transport_fee_payments` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fee_heads', 'is_transport_fee')) {
            Schema::table('fee_heads', function (Blueprint $table) {
                $table->dropColumn('is_transport_fee');
            });
        }

        if (Schema::hasColumn('transport_student_allocation', 'fee_payment_id')) {
            Schema::table('transport_student_allocation', function (Blueprint $table) {
                $table->dropConstrainedForeignId('fee_payment_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('fee_heads', function (Blueprint $table) {
            $table->boolean('is_transport_fee')->default(false)->after('is_taxable');
        });

        Schema::table('transport_student_allocation', function (Blueprint $table) {
            $table->foreignId('fee_payment_id')
                  ->nullable()
                  ->after('transport_fee')
                  ->constrained('fee_payments')
                  ->nullOnDelete();
        });
    }
};
