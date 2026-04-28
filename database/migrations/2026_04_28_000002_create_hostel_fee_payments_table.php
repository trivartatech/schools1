<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone receipts table for hostel fee collections.
 * Each row is an individual receipt with its own receipt_no generated
 * from the school's configurable hostel_receipt_* settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->index();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allocation_id')->constrained('hostel_students')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('discount',    10, 2)->default(0);
            $table->decimal('fine',        10, 2)->default(0);

            $table->date('payment_date');
            $table->string('payment_mode')->default('cash');
            $table->string('transaction_ref')->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gl_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'receipt_no']);
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_fee_payments');
    }
};
