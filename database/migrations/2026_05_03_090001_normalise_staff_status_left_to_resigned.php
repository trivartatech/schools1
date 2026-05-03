<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy imports stored status = 'left'; the canonical value is 'resigned'.
        DB::table('staff')
            ->where('status', 'left')
            ->update(['status' => 'resigned']);
    }

    public function down(): void
    {
        // Not reversible.
    }
};
