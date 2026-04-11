<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostel_visitors', function (Blueprint $table) {
            $table->string('pass_token')->nullable()->unique()->after('id');
            $table->string('visitor_photo')->nullable()->after('is_approved');
            $table->string('id_proof_type')->nullable()->after('id_proof');
        });
    }

    public function down(): void
    {
        Schema::table('hostel_visitors', function (Blueprint $table) {
            $table->dropColumn(['pass_token', 'visitor_photo', 'id_proof_type']);
        });
    }
};
