<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Problem (MySQL/MariaDB only):
 *   The unique index on sections(school_id, course_class_id, name) has no WHERE filter.
 *   A soft-deleted section still occupies its name slot, so the UI cannot re-create a
 *   section (e.g. "Class 5 – A") after it has been soft-deleted.
 *
 *   PostgreSQL and SQLite already received the correct partial index
 *   (WHERE deleted_at IS NULL) in migration 2026_03_28_042427.
 *
 * Fix (MySQL/MariaDB only):
 *   Replace the plain unique with a virtual sentinel column approach:
 *     deleted_at_key = IFNULL(UNIX_TIMESTAMP(deleted_at), 0)
 *   · Active rows  → sentinel 0   → (school_id, course_class_id, name, 0) must be unique ✔
 *   · Soft-deleted → sentinel > 0 → different timestamps allow multiple soft-deleted rows ✔
 *
 *   Edge case: two soft-deletes within the same second share the same sentinel. This is
 *   acceptable — it is prevented by the UI and is not reachable from normal school operations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return; // PostgreSQL / SQLite: partial index already correct.
        }

        // Mirror the FK-backing safeguard from the class_subjects migration: if MySQL was
        // using the composite unique as the FK-backing index for school_id or course_class_id
        // (both leftmost candidates), dropping it raises error 1553. Add dedicated indexes
        // first when missing — idempotent across servers.
        $ensureLeftmostIndex = function (string $column, string $indexName): void {
            $hasLeftmost = DB::selectOne(
                "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'sections'
                    AND COLUMN_NAME  = ?
                    AND SEQ_IN_INDEX = 1
                  LIMIT 1",
                [$column]
            );
            if (!$hasLeftmost) {
                DB::statement("ALTER TABLE sections ADD INDEX `{$indexName}` (`{$column}`)");
            }
        };
        $ensureLeftmostIndex('school_id',       'sections_school_id_index');
        $ensureLeftmostIndex('course_class_id', 'sections_course_class_id_index');

        // Drop the plain unique (blocks soft-deleted names).
        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique('idx_sections_school_class_name_unique');
        });

        // Virtual sentinel: 0 when active, UNIX_TIMESTAMP when soft-deleted.
        DB::statement('ALTER TABLE sections ADD COLUMN deleted_at_key BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(UNIX_TIMESTAMP(deleted_at), 0)) VIRTUAL');

        // New unique: only active rows (sentinel 0) compete for the name slot.
        DB::statement('ALTER TABLE sections ADD UNIQUE INDEX idx_sections_school_class_name_unique (school_id, course_class_id, name, deleted_at_key)');
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE sections DROP INDEX idx_sections_school_class_name_unique');
        DB::statement('ALTER TABLE sections DROP COLUMN deleted_at_key');

        Schema::table('sections', function (Blueprint $table) {
            $table->unique(['school_id', 'course_class_id', 'name'], 'idx_sections_school_class_name_unique');
        });
    }
};
