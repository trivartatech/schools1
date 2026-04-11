<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('transport_vehicles')->nullOnDelete();
            $table->foreignId('stop_id')->nullable()->constrained('transport_stops')->nullOnDelete();
            $table->date('date');
            $table->enum('trip_type', ['pickup', 'drop']);
            $table->enum('status', ['present', 'absent', 'late'])->default('present');
            $table->time('boarded_at')->nullable();
            $table->time('alighted_at')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'date', 'trip_type'], 'transport_att_unique');
            $table->index(['school_id', 'date']);
            $table->index(['route_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_attendance');
    }
};
