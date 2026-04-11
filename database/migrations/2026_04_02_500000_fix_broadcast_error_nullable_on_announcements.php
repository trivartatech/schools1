<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fix_announcement_error_type migration changed broadcast_error to json
     * using ->change() without ->nullable(), which silently dropped the nullable
     * constraint. This migration restores it so announcements can be created
     * without a broadcast_error value.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->json('broadcast_error')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->json('broadcast_error')->nullable(false)->change();
        });
    }
};
