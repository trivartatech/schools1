<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX #6: transaction_no was globally unique, meaning two different schools
 * both generating "TXN-2026-0001" would cause a DB-level unique constraint
 * violation. Replace with a per-school composite unique index.
 *
 * The same issue existed on fee_payments.receipt_no — also fixed here.
 * The FeePayment model already scopes its safety-loop check to school_id,
 * so functionally it was correct; the DB constraint just needed updating.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── transactions: transaction_no unique per school ────────────────
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['transaction_no']);
            $table->unique(['school_id', 'transaction_no'], 'transactions_school_txn_no_unique');
        });

        // ── fee_payments: receipt_no unique per school ────────────────────
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_no']);
            $table->unique(['school_id', 'receipt_no'], 'fee_payments_school_receipt_no_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_school_txn_no_unique');
            $table->unique('transaction_no');
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropUnique('fee_payments_school_receipt_no_unique');
            $table->unique('receipt_no');
        });
    }
};
