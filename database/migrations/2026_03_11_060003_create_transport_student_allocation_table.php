<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_student_allocation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->foreignId('stop_id')->constrained('transport_stops')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('transport_vehicles')->nullOnDelete();
            $table->decimal('transport_fee', 10, 2)->default(0)
                ->comment('Snapshot of stop fee at time of allocation');
            $table->enum('pickup_type', ['pickup', 'drop', 'both'])->default('both');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('student_id');
            $table->index('vehicle_id');
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_student_allocation');
    }
};
