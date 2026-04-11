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
            $table->dropColumn(['applicable_fee_heads', 'is_one_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_concessions', function (Blueprint $table) {
            $table->json('applicable_fee_heads')->nullable()->after('value');
            $table->boolean('is_one_time')->default(false)->after('applicable_fee_heads');
        });
    }
};
