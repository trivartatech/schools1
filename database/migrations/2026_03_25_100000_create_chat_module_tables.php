<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Chat Conversations (one-to-one, group, broadcast) ──────────────
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('direct'); // direct | group | broadcast
            $table->string('name')->nullable();         // group/broadcast name
            $table->string('group_type')->nullable();   // section_group | custom_group | broadcast
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_system_managed')->default(false); // auto-created section groups
            $table->boolean('is_pinned')->default(false);
            $table->text('description')->nullable();
            $table->string('avatar')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Conversation Participants ───────────────────────────────────────
        Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // admin | member
            $table->boolean('is_muted')->default(false);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        // ── Messages ───────────────────────────────────────────────────────
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('text'); // text | image | pdf | voice | system
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->bigInteger('attachment_size')->nullable();
            $table->foreignId('reply_to_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at_for_all')->nullable(); // soft delete for all
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        // ── Message Read Receipts ──────────────────────────────────────────
        Schema::create('chat_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['message_id', 'user_id']);
        });

        // ── Typing Indicators (ephemeral, used for polling) ────────────────
        Schema::create('chat_typing_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('typed_at')->useCurrent();

            $table->unique(['conversation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_typing_indicators');
        Schema::dropIfExists('chat_message_reads');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('chat_conversations');
    }
};
