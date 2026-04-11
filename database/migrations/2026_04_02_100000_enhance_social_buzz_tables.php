<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add pin support to posts
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('is_approved');
            $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            $table->foreignId('pinned_by')->nullable()->after('pinned_at')->constrained('users')->nullOnDelete();
        });

        // Bookmarks table
        Schema::create('post_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_bookmarks');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['pinned_by']);
            $table->dropColumn(['is_pinned', 'pinned_at', 'pinned_by']);
        });
    }
};
