<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds transaction lifecycle support:
 *
 *  status      — draft | posted | void
 *                Default 'posted' so ALL existing transactions remain visible
 *                in financial statements without any data migration.
 *
 *  reversal_of — nullable FK back to transactions.id
 *                Populated when a transaction is a reversal of another.
 *                The original transaction gets status='void'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('status', ['draft', 'posted', 'void'])
                  ->default('posted')
                  ->after('type');

            $table->foreignId('reversal_of')
                  ->nullable()
                  ->after('status')
                  ->constrained('transactions')
                  ->nullOnDelete();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['reversal_of']);
            $table->dropIndex(['school_id', 'status']);
            $table->dropColumn(['status', 'reversal_of']);
        });
    }
};
