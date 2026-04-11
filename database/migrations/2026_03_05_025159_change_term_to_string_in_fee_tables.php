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
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->string('term', 50)->default('annual')->change();
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->string('term', 50)->default('annual')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            // SQLite doesn't strictly enforce enum to string reversions in a way that matters for downgrades easily, 
            // so we leave it as string(50) or revert to strict enum if using MySQL
            // $table->enum('term', ['annual', 'term1', 'term2', 'term3', 'monthly', 'quarterly', 'half_yearly'])->default('annual')->change();
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            // 
        });
    }
};
