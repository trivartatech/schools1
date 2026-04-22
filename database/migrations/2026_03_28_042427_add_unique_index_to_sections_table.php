<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('sections', function (Blueprint $table) {
                $table->unique(['school_id', 'course_class_id', 'name'], 'idx_sections_school_class_name_unique');
            });
        } else {
            DB::statement('CREATE UNIQUE INDEX idx_sections_school_class_name_unique ON sections (school_id, course_class_id, name) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropUnique('idx_sections_school_class_name_unique');
            });
        } else {
            DB::statement('DROP INDEX idx_sections_school_class_name_unique');
        }
    }
};
