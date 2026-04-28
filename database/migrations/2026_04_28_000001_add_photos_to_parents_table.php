<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            if (!Schema::hasColumn('parents', 'father_photo')) {
                $table->string('father_photo')->nullable()->after('mother_qualification');
            }
            if (!Schema::hasColumn('parents', 'mother_photo')) {
                $table->string('mother_photo')->nullable()->after('father_photo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            if (Schema::hasColumn('parents', 'mother_photo')) {
                $table->dropColumn('mother_photo');
            }
            if (Schema::hasColumn('parents', 'father_photo')) {
                $table->dropColumn('father_photo');
            }
        });
    }
};
