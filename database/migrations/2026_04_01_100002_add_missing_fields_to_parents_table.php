<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->string('guardian_phone')->nullable()->after('guardian_name');
            $table->string('father_qualification')->nullable()->after('father_occupation');
            $table->string('mother_qualification')->nullable()->after('mother_occupation');
        });
    }

    public function down(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->dropColumn(['guardian_phone', 'father_qualification', 'mother_qualification']);
        });
    }
};
