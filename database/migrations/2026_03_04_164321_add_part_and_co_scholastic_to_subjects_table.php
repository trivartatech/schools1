<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            // Sub-type for multi-part subjects: "Part A", "Part B", "Language 1", etc.
            $table->string('part', 50)->nullable()->after('code');
            // Co-scholastic subjects are graded (A+/A/B etc) rather than marks-based
            $table->boolean('is_co_scholastic')->default(false)->after('is_elective');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['part', 'is_co_scholastic']);
        });
    }
};
