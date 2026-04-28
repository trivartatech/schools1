<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items of a stationary return event.
 *
 * condition  = good | damaged
 * restock    = true → increment stationary_items.current_stock when accepted
 *              false → write-off (typically for damaged items)
 * line_refund = qty_returned × refund_unit_price (used to validate header
 *               refund_amount and to show per-line refund in the UI)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stationary_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('stationary_returns')->cascadeOnDelete();
            $table->foreignId('allocation_item_id')->constrained('stationary_allocation_items')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('stationary_items')->cascadeOnDelete();
            $table->integer('qty_returned');
            $table->enum('condition', ['good', 'damaged'])->default('good');
            $table->boolean('restock')->default(true);
            $table->decimal('refund_unit_price', 10, 2)->default(0);
            $table->decimal('line_refund', 10, 2)->default(0);
            $table->timestamps();

            $table->index('return_id');
            $table->index('allocation_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stationary_return_items');
    }
};
