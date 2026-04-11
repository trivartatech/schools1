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
        \Illuminate\Support\Facades\Schema::table('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('user_type')->default('student')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No safe downgrade
    }
};
