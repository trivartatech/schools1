<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy imports stored user_type = 'staff'; the canonical value is 'teacher'.
        DB::table('users')
            ->where('user_type', 'staff')
            ->update(['user_type' => 'teacher']);
    }

    public function down(): void
    {
        // Not reversible — we don't know which rows were originally 'staff'.
    }
};
