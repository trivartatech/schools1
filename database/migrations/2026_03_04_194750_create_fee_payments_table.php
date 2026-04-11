<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // fee_payments stores actual student fee payments / receipts
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();    // FEE-2025-00001

            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount_due',  10, 2);
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('discount',    10, 2)->default(0);
            $table->decimal('fine',        10, 2)->default(0);
            $table->decimal('balance',     10, 2)->default(0);     // amount_due - discount - amount_paid

            $table->enum('term', ['annual', 'term1', 'term2', 'term3', 'monthly', 'quarterly', 'half_yearly'])->default('annual');
            $table->date('payment_date');
            $table->enum('payment_mode', ['cash', 'cheque', 'online', 'upi', 'dd', 'card'])->default('cash');
            $table->string('transaction_ref')->nullable();         // Cheque/UTR number

            $table->enum('status', ['paid', 'partial', 'due', 'waived'])->default('paid');
            $table->text('remarks')->nullable();

            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
