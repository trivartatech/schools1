<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stationary item master — products the school sells to students
 * (notebooks, pens, books, geometry sets, etc.).
 *
 * Stock fields are decremented on issuance and incremented on returns
 * (when restock=true). Independent from the generic ItemStore module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stationary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->string('hsn_code')->nullable();
            $table->integer('current_stock')->default(0);
            $table->integer('min_stock')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'name']);
            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stationary_items');
    }
};
