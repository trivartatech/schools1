<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('ledger_type_id')->constrained('ledger_types')->onDelete('restrict');
            $table->string('name');
            $table->string('code')->nullable();          // optional account code e.g. 1001
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->enum('opening_balance_type', ['debit', 'credit'])->default('debit');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // system ledgers cannot be deleted
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'ledger_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
