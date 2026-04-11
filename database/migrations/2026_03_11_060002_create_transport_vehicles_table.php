<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_number');
            $table->string('vehicle_name')->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('conductor_name')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->foreignId('route_id')->nullable()->constrained('transport_routes')->nullOnDelete();
            $table->string('gps_device_id')->nullable()->unique();
            $table->date('insurance_expiry')->nullable();
            $table->date('fitness_expiry')->nullable();
            $table->date('pollution_expiry')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'vehicle_number']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_vehicles');
    }
};
