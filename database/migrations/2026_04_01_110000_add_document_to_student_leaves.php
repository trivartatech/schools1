<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_leaves', function (Blueprint $table) {
            // Stored path relative to storage/app/private disk
            $table->string('document_path')->nullable()->after('remarks');
            // Original filename shown to the user
            $table->string('document_original_name')->nullable()->after('document_path');
            // MIME type for icon display (pdf / image)
            $table->string('document_mime')->nullable()->after('document_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('student_leaves', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_original_name', 'document_mime']);
        });
    }
};
