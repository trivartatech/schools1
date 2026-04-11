<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Posts Table
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('content')->nullable();
            $table->string('visibility')->default('school'); // school, class, staff
            $table->foreignId('class_id')->nullable()->constrained('course_classes')->onDelete('cascade');
            $table->boolean('is_approved')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['school_id', 'visibility']);
        });

        // 2. Post Media Table (Images/Videos)
        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable(); // image/jpeg, video/mp4
            $table->string('thumbnail_path')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. Post Likes (Reactions)
        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type')->default('like'); // for future-proofing with multi-reactions
            $table->timestamps();

            $table->unique(['post_id', 'user_id', 'type']);
        });

        // 4. Post Comments
        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('post_comments')->onDelete('cascade');
            $table->text('comment');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
    }
};
