<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fee_heads', 'is_hostel_fee')) {
            Schema::table('fee_heads', function (Blueprint $table) {
                $table->dropColumn('is_hostel_fee');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fee_heads', 'is_hostel_fee')) {
            Schema::table('fee_heads', function (Blueprint $table) {
                $table->boolean('is_hostel_fee')->default(false)->after('sort_order');
            });
        }
    }
};
