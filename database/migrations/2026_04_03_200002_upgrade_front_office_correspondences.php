<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondences', function (Blueprint $table) {
            $table->boolean('acknowledged')->default(false)->after('notes');
            $table->timestamp('acknowledged_at')->nullable()->after('acknowledged');
            $table->string('acknowledged_by', 255)->nullable()->after('acknowledged_at');
            $table->string('delivery_status', 20)->default('pending')->after('acknowledged_by');
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table) {
            $table->dropColumn(['acknowledged', 'acknowledged_at', 'acknowledged_by', 'delivery_status']);
        });
    }
};
