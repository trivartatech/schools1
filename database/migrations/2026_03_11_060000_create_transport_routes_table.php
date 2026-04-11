<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('route_name');
            $table->string('route_code');
            $table->string('start_location')->nullable();
            $table->string('end_location')->nullable();
            $table->decimal('distance', 8, 2)->nullable()->comment('Distance in KM');
            $table->string('estimated_time')->nullable()->comment('e.g. 45 mins');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('school_id');
            $table->unique(['school_id', 'route_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_routes');
    }
};
