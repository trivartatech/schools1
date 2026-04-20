<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollover_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rollover_run_id')->constrained()->cascadeOnDelete();

            $table->string('phase', 40);
            $table->string('item_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();

            $table->enum('status', ['pending', 'success', 'skipped', 'failed'])->default('pending');
            $table->text('note')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['rollover_run_id', 'phase']);
            $table->index(['item_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rollover_run_items');
    }
};
