<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Problem: the unique index on class_subjects(course_class_id, section_id, subject_id)
 * does NOT prevent duplicate class-level rows (section_id = NULL) on any database engine,
 * because all standard SQL engines treat NULL as distinct from every other value —
 * including another NULL — in a unique index.
 *
 * Fix strategy:
 *   MySQL/MariaDB → add a virtual generated column `section_id_key = COALESCE(section_id, 0)`.
 *                   Since real section IDs are auto-increment starting at 1, the sentinel 0
 *                   is safe. Replace the unique with one on (course_class_id, section_id_key, subject_id).
 *
 *   PostgreSQL / SQLite → split into two partial unique indexes:
 *                   · unique_cs_section_level  on (course_class_id, section_id, subject_id) WHERE section_id IS NOT NULL
 *                   · unique_cs_class_level    on (course_class_id, subject_id)             WHERE section_id IS NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Some MySQL servers used the composite unique as the FK-backing index for
            // course_class_id (leftmost column). Dropping it then fails with error 1553
            // "Cannot drop index ... needed in a foreign key constraint". Add a dedicated
            // single-column index for any FK column that doesn't already have one as its
            // leftmost. Idempotent across servers where Laravel's foreignId() may or may
            // not have already created such an index.
            $ensureLeftmostIndex = function (string $column, string $indexName): void {
                // Exclude the unique we're about to drop. Otherwise its leftmost column
                // (course_class_id) would short-circuit the check and we'd skip adding
                // the very backing index we need.
                $hasLeftmost = DB::selectOne(
                    "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME   = 'class_subjects'
                        AND COLUMN_NAME  = ?
                        AND SEQ_IN_INDEX = 1
                        AND INDEX_NAME  != 'unique_class_section_subject'
                      LIMIT 1",
                    [$column]
                );
                if (!$hasLeftmost) {
                    DB::statement("ALTER TABLE class_subjects ADD INDEX `{$indexName}` (`{$column}`)");
                }
            };
            $ensureLeftmostIndex('course_class_id', 'class_subjects_course_class_id_index');
            $ensureLeftmostIndex('section_id',      'class_subjects_section_id_index');
            $ensureLeftmostIndex('subject_id',      'class_subjects_subject_id_index');

            // Now safe to drop the broken plain unique.
            DB::statement('ALTER TABLE class_subjects DROP INDEX unique_class_section_subject');
            // Virtual column: NULL section_id → sentinel 0; real IDs pass through unchanged.
            DB::statement('ALTER TABLE class_subjects ADD COLUMN section_id_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(section_id, 0)) VIRTUAL');
            // New unique enforces class-level uniqueness (sentinel 0) AND section-level uniqueness.
            DB::statement('ALTER TABLE class_subjects ADD UNIQUE INDEX unique_class_section_subject (course_class_id, section_id_key, subject_id)');
        } else {
            // Drop the original plain unique (created by Blueprint in the base migration).
            // PostgreSQL names it after the table+columns; Blueprint used the explicit name.
            DB::statement('DROP INDEX IF EXISTS unique_class_section_subject');
            try {
                DB::statement('ALTER TABLE class_subjects DROP CONSTRAINT unique_class_section_subject');
            } catch (\Throwable) {
                // Already dropped above, or was an index not a constraint — ignore.
            }
            // Partial index for section-level rows (section_id IS NOT NULL).
            DB::statement('CREATE UNIQUE INDEX unique_cs_section_level ON class_subjects (course_class_id, section_id, subject_id) WHERE section_id IS NOT NULL');
            // Partial index for class-level rows (section_id IS NULL).
            // Only (course_class_id, subject_id) needed here — section_id is always NULL in this slice.
            DB::statement('CREATE UNIQUE INDEX unique_cs_class_level ON class_subjects (course_class_id, subject_id) WHERE section_id IS NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE class_subjects DROP INDEX unique_class_section_subject');
            DB::statement('ALTER TABLE class_subjects DROP COLUMN section_id_key');
            DB::statement('ALTER TABLE class_subjects ADD UNIQUE INDEX unique_class_section_subject (course_class_id, section_id, subject_id)');
        } else {
            DB::statement('DROP INDEX IF EXISTS unique_cs_section_level');
            DB::statement('DROP INDEX IF EXISTS unique_cs_class_level');
            DB::statement('CREATE UNIQUE INDEX unique_class_section_subject ON class_subjects (course_class_id, section_id, subject_id)');
        }
    }
};
