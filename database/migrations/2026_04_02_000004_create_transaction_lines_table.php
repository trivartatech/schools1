<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
            $table->foreignId('ledger_id')->constrained('ledgers')->onDelete('restrict');
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'type']);
            $table->index('ledger_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_lines');
    }
};
