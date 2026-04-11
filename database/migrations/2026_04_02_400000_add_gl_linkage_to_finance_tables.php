<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── fee_payments ──────────────────────────────────────────
        Schema::table('fee_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_payments', 'gl_transaction_id')) {
                $table->foreignId('gl_transaction_id')
                    ->nullable()->after('collected_by')
                    ->constrained('transactions')->nullOnDelete();
            }
        });

        // ── expenses ──────────────────────────────────────────────
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'gl_transaction_id')) {
                $table->foreignId('gl_transaction_id')
                    ->nullable()->after('recorded_by')
                    ->constrained('transactions')->nullOnDelete();
            }
        });

        // ── payrolls ──────────────────────────────────────────────
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'gl_transaction_id')) {
                $table->foreignId('gl_transaction_id')
                    ->nullable()->after('payment_mode')
                    ->constrained('transactions')->nullOnDelete();
            }
        });

        // ── expense_categories — per-category ledger mapping ──────
        Schema::table('expense_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_categories', 'ledger_id')) {
                $table->foreignId('ledger_id')
                    ->nullable()->after('name')
                    ->constrained('ledgers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_payments',       fn($t) => $t->dropConstrainedForeignId('gl_transaction_id'));
        Schema::table('expenses',           fn($t) => $t->dropConstrainedForeignId('gl_transaction_id'));
        Schema::table('payrolls',           fn($t) => $t->dropConstrainedForeignId('gl_transaction_id'));
        Schema::table('expense_categories', fn($t) => $t->dropConstrainedForeignId('ledger_id'));
    }
};
