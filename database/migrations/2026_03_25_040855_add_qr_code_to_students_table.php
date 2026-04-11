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
        Schema::table('students', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        // Back-fill UUIDs for existing entries
        $students = \Illuminate\Support\Facades\DB::table('students')->whereNull('uuid')->get();
        foreach ($students as $student) {
            \Illuminate\Support\Facades\DB::table('students')
                ->where('id', $student->id)
                ->update(['uuid' => (string) \Illuminate\Support\Str::uuid()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
