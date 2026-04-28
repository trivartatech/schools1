<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log of stationary handover events.
 * Each row is one event (admin handed items to a student on a given date).
 * Soft-deleted rows mean the event was voided; line items reverse on void.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stationary_issuances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allocation_id')->constrained('stationary_student_allocation')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'allocation_id']);
            $table->index(['school_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stationary_issuances');
    }
};
