<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add subject_type_id FK to subjects (proper relational link instead of raw string label)
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('subject_type_id')
                  ->nullable()
                  ->after('code')
                  ->constrained('subject_types')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['subject_type_id']);
            $table->dropColumn('subject_type_id');
        });
    }
};
