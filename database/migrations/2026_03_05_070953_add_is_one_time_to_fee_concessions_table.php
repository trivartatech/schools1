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
        Schema::table('fee_concessions', function (Blueprint $table) {
            $table->boolean('is_one_time')->default(false)->after('applicable_fee_heads');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_concessions', function (Blueprint $table) {
            $table->dropColumn('is_one_time');
        });
    }
};
