<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add geofence configuration to schools
        Schema::table('schools', function (Blueprint $table) {
            $table->decimal('geo_fence_lat', 10, 7)->nullable()->after('settings');
            $table->decimal('geo_fence_lng', 10, 7)->nullable()->after('geo_fence_lat');
            $table->unsignedInteger('geo_fence_radius')->default(200)->after('geo_fence_lng'); // metres
        });

        // Add punch geolocation to staff_attendances
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->decimal('punch_in_lat', 10, 7)->nullable()->after('check_out');
            $table->decimal('punch_in_lng', 10, 7)->nullable()->after('punch_in_lat');
            $table->decimal('punch_out_lat', 10, 7)->nullable()->after('punch_in_lng');
            $table->decimal('punch_out_lng', 10, 7)->nullable()->after('punch_out_lat');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['geo_fence_lat', 'geo_fence_lng', 'geo_fence_radius']);
        });

        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->dropColumn(['punch_in_lat', 'punch_in_lng', 'punch_out_lat', 'punch_out_lng']);
        });
    }
};
