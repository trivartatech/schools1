<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            // Address breakdown (to match students table)
            $table->string('city')->nullable()->after('student_address');
            $table->string('state')->nullable()->after('city');
            $table->string('pincode')->nullable()->after('state');

            // Student extras
            $table->string('nationality')->nullable()->default('Indian')->after('aadhaar_no');
            $table->string('emergency_contact_name')->nullable()->after('pincode');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');

            // Parent extras
            $table->string('guardian_email')->nullable()->after('guardian_name');
            $table->string('guardian_phone')->nullable()->after('guardian_email');
            $table->string('father_qualification')->nullable()->after('father_occupation');
            $table->string('mother_qualification')->nullable()->after('mother_occupation');

            // Previous school info
            $table->string('previous_school')->nullable()->after('parent_address');
            $table->string('previous_class')->nullable()->after('previous_school');

            // Socio-economic
            $table->string('annual_income')->nullable()->after('previous_class');
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->dropColumn([
                'city', 'state', 'pincode',
                'nationality', 'emergency_contact_name', 'emergency_contact_phone',
                'guardian_email', 'guardian_phone',
                'father_qualification', 'mother_qualification',
                'previous_school', 'previous_class', 'annual_income',
            ]);
        });
    }
};
