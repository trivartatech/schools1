<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items composing a stationary allocation (the kit).
 *
 * qty_entitled = total kit size for this student
 * qty_collected = cumulative items physically issued (incremented by issuances,
 *                 decremented by returns)
 * unit_price + line_total are snapshots so that later price changes on the
 * item master don't retroactively alter past allocations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stationary_allocation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->constrained('stationary_student_allocation')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('stationary_items')->cascadeOnDelete();

            $table->integer('qty_entitled')->default(0);
            $table->integer('qty_collected')->default(0);

            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->timestamps();

            $table->index('allocation_id');
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stationary_allocation_items');
    }
};
