<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header row for a student's stationary kit allocation.
 *
 * Money flow         : total_amount (= Σ line.qty_entitled × unit_price),
 *                      amount_paid, discount, fine, balance, payment_status.
 * Physical handover  : collection_status, last_issued_date — separate from
 *                      payment, since a student may pay upfront and collect
 *                      items gradually, or vice versa.
 *
 * One row per student per academic year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stationary_student_allocation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid',  10, 2)->default(0);
            $table->decimal('discount',     10, 2)->default(0);
            $table->decimal('fine',         10, 2)->default(0);
            $table->decimal('balance',      10, 2)->default(0);

            $table->enum('payment_status',    ['unpaid', 'partial', 'paid', 'waived'])->default('unpaid');
            $table->enum('collection_status', ['none', 'partial', 'complete'])->default('none');

            $table->date('last_payment_date')->nullable();
            $table->date('last_issued_date')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['school_id', 'payment_status']);
            $table->index(['school_id', 'collection_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stationary_student_allocation');
    }
};
