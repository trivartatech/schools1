<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();

            // Razorpay order details
            $table->string('gateway', 20)->default('razorpay');
            $table->string('gateway_order_id')->unique()->comment('Razorpay order_id');
            $table->string('gateway_payment_id')->nullable()->comment('Razorpay payment_id after success');
            $table->string('gateway_signature')->nullable()->comment('Razorpay signature for verification');

            // Amount in paise (Razorpay works in smallest currency unit)
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 3)->default('INR');

            // What the parent chose to pay (JSON array of fee head selections)
            $table->json('fee_items')->comment('Array of {fee_head_id, term, amount}');

            // Status flow: created → paid → processed | failed | expired
            $table->enum('status', ['created', 'paid', 'processed', 'failed', 'expired'])->default('created');

            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'academic_year_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payment_orders');
    }
};
