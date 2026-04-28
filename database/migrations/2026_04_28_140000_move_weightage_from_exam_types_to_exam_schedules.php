<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->decimal('weightage', 5, 2)->default(100.00)->after('exam_type_id')
                  ->comment('Weightage % for this schedule in cumulative reports');
        });

        DB::statement('
            UPDATE exam_schedules es
            INNER JOIN exam_types et ON et.id = es.exam_type_id
            SET es.weightage = et.weightage
        ');

        Schema::table('exam_types', function (Blueprint $table) {
            $table->dropColumn('weightage');
        });
    }

    public function down(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->decimal('weightage', 5, 2)->default(100.00)->after('display_name');
        });

        DB::statement('
            UPDATE exam_types et
            INNER JOIN (
                SELECT exam_type_id, MAX(weightage) AS w
                FROM exam_schedules
                GROUP BY exam_type_id
            ) es ON es.exam_type_id = et.id
            SET et.weightage = es.w
        ');

        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropColumn('weightage');
        });
    }
};
