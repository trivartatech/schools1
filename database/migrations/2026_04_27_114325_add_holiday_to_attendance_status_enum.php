<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aligns the student attendances.status enum with staff_attendances by adding
 * 'holiday'. Without this, calling /mobile/attendance/mark with status='holiday'
 * on a student row got a 422 — even though the staff equivalent accepted it,
 * and the mobile UI showed 'Holiday' as a picker option.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendances MODIFY status ENUM('present','absent','late','half_day','leave','holiday') NOT NULL");
    }

    public function down(): void
    {
        // Down is best-effort: any rows already set to 'holiday' would be dropped
        // back to NULL/'present' by MySQL on a strict enum shrink. Convert them
        // to 'leave' first so they're preserved as a non-Present status.
        DB::statement("UPDATE attendances SET status = 'leave' WHERE status = 'holiday'");
        DB::statement("ALTER TABLE attendances MODIFY status ENUM('present','absent','late','half_day','leave') NOT NULL");
    }
};
