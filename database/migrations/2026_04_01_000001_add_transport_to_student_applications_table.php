<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->foreignId('transport_route_id')->nullable()->after('section_id')
                ->constrained('transport_routes')->nullOnDelete();
            $table->foreignId('transport_stop_id')->nullable()->after('transport_route_id')
                ->constrained('transport_stops')->nullOnDelete();
            $table->enum('transport_pickup_type', ['pickup', 'drop', 'both'])->nullable()->after('transport_stop_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->dropForeign(['transport_route_id']);
            $table->dropForeign(['transport_stop_id']);
            $table->dropColumn(['transport_route_id', 'transport_stop_id', 'transport_pickup_type']);
        });
    }
};
