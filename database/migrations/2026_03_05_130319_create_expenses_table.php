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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->enum('payment_mode', ['cash', 'cheque', 'online', 'upi', 'dd', 'card'])->default('cash');
            $table->string('transaction_ref')->nullable(); // Cheque/UTR no
            $table->string('title'); // Brief description like "Plumbing Repair"
            $table->text('description')->nullable();
            
            $table->string('attachment_path')->nullable(); // Bill/Invoice scan
            
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
