<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_id', 'type', 'body',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
        'reply_to_id', 'is_pinned', 'edited_at', 'deleted_at_for_all',
    ];

    protected $casts = [
        'is_pinned'          => 'boolean',
        'edited_at'          => 'datetime',
        'deleted_at_for_all' => 'datetime',
    ];

    protected $appends = ['attachment_url'];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_id');
    }

    public function reads()
    {
        return $this->hasMany(ChatMessageRead::class, 'message_id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if ($this->attachment_path) {
            return asset('storage/' . $this->attachment_path);
        }
        return null;
    }

    public function isDeletedForAll(): bool
    {
        return $this->deleted_at_for_all !== null;
    }
}
