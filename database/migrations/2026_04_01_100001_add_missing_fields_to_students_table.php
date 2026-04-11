<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('nationality')->nullable()->default('Indian')->after('aadhaar_no');
            $table->string('emergency_contact_name')->nullable()->after('pincode');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['nationality', 'emergency_contact_name', 'emergency_contact_phone']);
        });
    }
};
