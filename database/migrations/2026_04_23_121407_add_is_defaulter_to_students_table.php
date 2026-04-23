<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_defaulter')->default(false)->after('status');
            $table->index(['school_id', 'is_defaulter']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'is_defaulter']);
            $table->dropColumn('is_defaulter');
        });
    }
};
