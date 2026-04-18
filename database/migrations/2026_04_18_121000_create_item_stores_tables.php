<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->string('name');
            $table->string('location')->nullable();
            $table->unsignedBigInteger('incharge_staff_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('store_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('name');
            $table->string('unit', 50)->default('pcs');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('min_quantity', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('item_stores')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });

        Schema::create('store_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('item_id');
            $table->enum('type', ['in', 'out']);
            $table->decimal('quantity', 10, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('item_stores')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('store_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_transactions');
        Schema::dropIfExists('store_items');
        Schema::dropIfExists('item_stores');
    }
};
