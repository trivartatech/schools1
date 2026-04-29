<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Per-channel success/failure counts for the broadcast run, e.g.
            // {"recipients":120,"sent":118,"failed":2}. Null until first broadcast.
            $table->json('broadcast_stats')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('broadcast_stats');
        });
    }
};
