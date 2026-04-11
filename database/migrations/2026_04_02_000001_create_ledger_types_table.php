<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('name');          // Asset, Liability, Capital, Income, Expense
            $table->enum('nature', ['debit', 'credit']); // debit = Assets/Expenses; credit = Liabilities/Capital/Income
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // system types cannot be deleted
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_types');
    }
};
