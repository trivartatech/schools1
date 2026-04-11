<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('student_academic_histories', 'roll_no')) {
                // roll_no per class-section-year (the canonical per-year roll number)
                $table->string('roll_no', 20)->nullable()->after('section_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_histories', function (Blueprint $table) {
            if (Schema::hasColumn('student_academic_histories', 'roll_no')) {
                $table->dropColumn('roll_no');
            }
        });
    }
};
