<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Drop trust_id columns from child tables BEFORE dropping the trusts table.
        // SQLite 3.35+ native ALTER TABLE DROP COLUMN refuses to drop columns that
        // carry a FK constraint definition — even with foreign_keys = OFF. We must
        // remove the FK constraint first (forcing table reconstruction), then drop
        // the now-plain column. Combining dropForeign + dropColumn in one Blueprint
        // causes SQLite to apply both changes in a single table reconstruction pass.
        foreach (['organizations', 'schools', 'users'] as $tbl) {
            if (Schema::hasColumn($tbl, 'trust_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropForeign(['trust_id']);
                    $table->dropColumn('trust_id');
                });
            }
        }

        // Make organization_id nullable on schools (trust hierarchy is gone)
        if (Schema::hasColumn('schools', 'organization_id')) {
            Schema::table('schools', fn (Blueprint $table) =>
                $table->unsignedBigInteger('organization_id')->nullable()->change()
            );
        }

        Schema::dropIfExists('trusts');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration logic for now
    }
};
