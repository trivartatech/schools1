<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->string('stop_name');
            $table->string('stop_code')->nullable();
            $table->time('pickup_time')->nullable();
            $table->time('drop_time')->nullable();
            $table->decimal('distance_from_school', 8, 2)->nullable()->comment('In KM');
            $table->decimal('fee', 10, 2)->default(0);
            $table->unsignedInteger('stop_order')->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index('route_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_stops');
    }
};
