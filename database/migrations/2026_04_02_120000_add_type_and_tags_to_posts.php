<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('type')->default('update')->after('visibility');
            $table->json('tags')->nullable()->after('type');
            $table->unsignedInteger('shares_count')->default(0)->after('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['type', 'tags', 'shares_count']);
        });
    }
};
