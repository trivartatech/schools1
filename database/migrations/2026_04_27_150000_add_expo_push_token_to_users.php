<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Expo push token column for the React Native app.
 * Existing fcm_token column is kept untouched so a future FCM integration
 * can sit alongside Expo without another migration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'expo_push_token')) {
                $table->string('expo_push_token')->nullable()->after('fcm_token');
                $table->string('device_platform', 16)->nullable()->after('expo_push_token'); // ios | android | web
                $table->timestamp('push_token_updated_at')->nullable()->after('device_platform');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['expo_push_token', 'device_platform', 'push_token_updated_at'] as $col) {
                if (Schema::hasColumn('users', $col)) $table->dropColumn($col);
            }
        });
    }
};
