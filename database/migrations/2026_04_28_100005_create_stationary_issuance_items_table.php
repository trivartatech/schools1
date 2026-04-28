<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items of a stationary issuance event (one row per item handed over).
 * No soft-delete needed — voids cascade via the parent issuance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stationary_issuance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issuance_id')->constrained('stationary_issuances')->cascadeOnDelete();
            $table->foreignId('allocation_item_id')->constrained('stationary_allocation_items')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('stationary_items')->cascadeOnDelete();
            $table->integer('qty_issued');
            $table->timestamps();

            $table->index('issuance_id');
            $table->index('allocation_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stationary_issuance_items');
    }
};
