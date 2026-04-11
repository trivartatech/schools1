<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per vehicle. Upserted on each GPS ping.
        // This avoids querying millions of log rows for the live map.
        Schema::create('transport_vehicle_live_locations', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->primary()->constrained('transport_vehicles')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 6, 2)->default(0);
            $table->unsignedSmallInteger('heading')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_vehicle_live_locations');
    }
};
