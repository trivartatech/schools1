<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log of stationary return events.
 *
 * refund_amount > 0 with refund_mode in {cash, cheque} triggers a reverse GL
 * entry (Dr Stationary Income / Cr Cash) via StationaryReturnGLObserver.
 * refund_mode = 'adjust' means the refund is credited toward outstanding
 * balance — no money out, but allocation.amount_paid still drops.
 * refund_mode = 'none' means the items are returned with no refund at all
 * (e.g. damaged goods written off).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stationary_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allocation_id')->constrained('stationary_student_allocation')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('returned_at');
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->enum('refund_mode', ['cash', 'cheque', 'adjust', 'none'])->default('none');
            $table->foreignId('gl_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'allocation_id']);
            $table->index(['school_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stationary_returns');
    }
};
