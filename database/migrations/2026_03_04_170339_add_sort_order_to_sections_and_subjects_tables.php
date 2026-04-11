<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('capacity');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_co_scholastic');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
