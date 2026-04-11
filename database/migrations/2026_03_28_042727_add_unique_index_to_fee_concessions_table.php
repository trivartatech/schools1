<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Cleanup duplicates first to ensure migration succeeds
        $duplicates = \Illuminate\Support\Facades\DB::table('fee_concessions')
            ->select('school_id', 'academic_year_id', 'student_id', 'name', \Illuminate\Support\Facades\DB::raw('MIN(id) as keep_id'))
            ->groupBy('school_id', 'academic_year_id', 'student_id', 'name')
            ->having(\Illuminate\Support\Facades\DB::raw('COUNT(*)'), '>', 1)
            ->get();

        foreach ($duplicates as $row) {
            \Illuminate\Support\Facades\DB::table('fee_concessions')
                ->where('school_id', $row->school_id)
                ->where('academic_year_id', $row->academic_year_id)
                ->where('student_id', $row->student_id)
                ->where('name', $row->name)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        // 2. Add unique index
        Schema::table('fee_concessions', function (Blueprint $table) {
            $table->unique(['school_id', 'academic_year_id', 'student_id', 'name'], 'school_year_student_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_concessions', function (Blueprint $table) {
            $table->dropUnique('school_year_student_name_unique');
        });
    }
};
