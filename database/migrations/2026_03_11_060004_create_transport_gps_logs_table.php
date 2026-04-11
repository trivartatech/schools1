<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_gps_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('transport_vehicles')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 6, 2)->default(0)->comment('In km/h');
            $table->unsignedSmallInteger('heading')->nullable()->comment('0-359 degrees');
            $table->timestamp('timestamp');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vehicle_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_gps_logs');
    }
};
