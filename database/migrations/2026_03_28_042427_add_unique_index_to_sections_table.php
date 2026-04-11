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
        // Adding unique constraint on (school_id, course_class_id, name) for non-deleted sections
        // Note: SQLite and PostgreSQL support 'WHERE' in indexes.
        \Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX idx_sections_school_class_name_unique ON sections (school_id, course_class_id, name) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('DROP INDEX idx_sections_school_class_name_unique');
    }
};
