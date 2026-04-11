<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            // Only add if not already present (safe to re-run)
            if (!Schema::hasColumn('parents', 'guardian_email')) {
                $table->string('guardian_email')->nullable()->after('guardian_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            if (Schema::hasColumn('parents', 'guardian_email')) {
                $table->dropColumn('guardian_email');
            }
        });
    }
};
