<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the orphaned trust_id columns left behind after the trusts table
 * was dropped in 2026_03_31_064106_remove_trust_layer.php.
 *
 * The trusts table no longer exists, so these FK columns are dangling
 * references that break FK enforcement (e.g. in SQLite test environments).
 */
return new class extends Migration
{
    public function up(): void
    {
        // This cleanup is now handled inside 2026_03_31_064106_remove_trust_layer.php
        // which drops trust_id columns before dropping the trusts table.
        // This migration is kept as a no-op to preserve the migration history
        // on databases that already ran the original (incomplete) remove_trust_layer.
        Schema::disableForeignKeyConstraints();

        foreach (['organizations', 'schools', 'users'] as $tbl) {
            if (Schema::hasColumn($tbl, 'trust_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropForeign(['trust_id']);
                    $table->dropColumn('trust_id');
                });
            }
        }

        if (Schema::hasColumn('schools', 'organization_id')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->change();
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Intentionally empty — we cannot restore the trust_id values
        // since the trusts table no longer exists.
    }
};
