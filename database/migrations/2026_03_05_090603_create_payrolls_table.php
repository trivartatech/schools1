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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            
            $table->tinyInteger('month'); // 1-12
            $table->integer('year'); // e.g. 2026
            
            $table->decimal('basic_pay', 10, 2)->default(0);
            $table->json('allowances')->nullable(); // e.g. [{"name": "HRA", "amount": 5000}]
            $table->json('deductions')->nullable(); // e.g. [{"name": "PF", "amount": 1800}]
            $table->decimal('net_salary', 10, 2)->default(0);
            
            $table->string('status')->default('generated'); // generated, paid
            $table->date('payment_date')->nullable();
            $table->string('payment_mode')->nullable(); // Bank Transfer, Cash, Cheque
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
