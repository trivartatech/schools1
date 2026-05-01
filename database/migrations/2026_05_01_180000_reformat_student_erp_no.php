<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reformat existing student.erp_no values from "<year>/<seq>" (e.g. "2025-26/0001")
 * to "ERP_<year>_<seq>" (e.g. "ERP_2025-26_0001").
 *
 * The new format is filename-safe so the same value can be used as a photo
 * filename for bulk photo upload — slashes are forbidden by the OS.
 *
 * Only rows that match the OLD pattern are touched, so re-running is a no-op.
 */
return new class extends Migration {
    public function up(): void
    {
        // Old pattern: 4-digit year, hyphen, 2-digit year, slash, digits
        // e.g. "2025-26/0001"
        DB::statement(<<<'SQL'
            UPDATE students
            SET erp_no = CONCAT('ERP_', REPLACE(erp_no, '/', '_'))
            WHERE erp_no REGEXP '^[0-9]{4}-[0-9]{2}/[0-9]+$'
        SQL);
    }

    public function down(): void
    {
        // Reverse: strip the ERP_ prefix and put the slash back.
        DB::statement(<<<'SQL'
            UPDATE students
            SET erp_no = CONCAT(
                SUBSTRING(erp_no, 5, 7),
                '/',
                SUBSTRING(erp_no, 13)
            )
            WHERE erp_no REGEXP '^ERP_[0-9]{4}-[0-9]{2}_[0-9]+$'
        SQL);
    }
};
