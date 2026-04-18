<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->after('supplier');
            $table->unsignedBigInteger('store_id')->nullable()->after('supplier_id');

            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('store_id')->references('id')->on('item_stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['store_id']);
            $table->dropColumn(['supplier_id', 'store_id']);
        });
    }
};
