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
 *     deleted_at_key = IF(deleted_at IS NULL, 0, id)
 *   · Active rows  → sentinel 0   → (school_id, course_class_id, name, 0) must be unique ✔
 *   · Soft-deleted → sentinel = id → ids are unique, so soft-deletes never collide ✔
 *
 *   We use `id` (not UNIX_TIMESTAMP(deleted_at)) because UNIX_TIMESTAMP is on MySQL 8.0+'s
 *   disallowed-functions list for generated columns — its result depends on session timezone,
 *   so MySQL refuses to index it. The row's own id is deterministic, unique, and already
 *   present, which sidesteps the timezone restriction *and* the same-second collision edge
 *   case the timestamp approach had.
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
            // Exclude the unique we're about to drop — its leftmost column (school_id)
            // would otherwise short-circuit the check and we'd skip adding a backing index.
            $hasLeftmost = DB::selectOne(
                "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'sections'
                    AND COLUMN_NAME  = ?
                    AND SEQ_IN_INDEX = 1
                    AND INDEX_NAME  != 'idx_sections_school_class_name_unique'
                  LIMIT 1",
                [$column]
            );
            if (!$hasLeftmost) {
                DB::statement("ALTER TABLE sections ADD INDEX `{$indexName}` (`{$column}`)");
            }
        };
        $ensureLeftmostIndex('school_id',       'sections_school_id_index');
        $ensureLeftmostIndex('course_class_id', 'sections_course_class_id_index');

        // Steps below are individually idempotent. A prior failed run could have left the
        // table mid-migration: the original unique already dropped, the new column not yet
        // added. Re-running picks up cleanly.

        // 1. Drop the plain unique if it still exists.
        $uniqueExists = DB::selectOne(
            "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'sections'
                AND INDEX_NAME   = 'idx_sections_school_class_name_unique'
              LIMIT 1"
        );
        if ($uniqueExists) {
            DB::statement('ALTER TABLE sections DROP INDEX idx_sections_school_class_name_unique');
        }

        // 2. Add the sentinel column if it doesn't exist already.
        //    0 for active rows (NULL deleted_at), id for soft-deleted (always unique).
        $columnExists = DB::selectOne(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'sections'
                AND COLUMN_NAME  = 'deleted_at_key'
              LIMIT 1"
        );
        if (!$columnExists) {
            DB::statement('ALTER TABLE sections ADD COLUMN deleted_at_key BIGINT UNSIGNED GENERATED ALWAYS AS (IF(deleted_at IS NULL, 0, id)) VIRTUAL');
        }

        // 3. Re-add the unique with the sentinel column included.
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
