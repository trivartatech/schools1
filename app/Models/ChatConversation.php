<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatConversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'type', 'name', 'group_type', 'section_id',
        'is_system_managed', 'is_pinned', 'description', 'avatar', 'created_by',
    ];

    protected $casts = [
        'is_system_managed' => 'boolean',
        'is_pinned'         => 'boolean',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants()
    {
        return $this->hasMany(ChatParticipant::class, 'conversation_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_participants', 'conversation_id', 'user_id')
                    ->withPivot('role', 'is_muted', 'last_read_at', 'joined_at');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany();
    }

    /** Unread count for a specific user */
    public function unreadCountFor(int $userId): int
    {
        $participant = $this->participants->firstWhere('user_id', $userId);
        if (!$participant) return 0;

        return $this->messages()
            ->whereNull('deleted_at_for_all')
            ->where('sender_id', '!=', $userId)
            ->where(function ($q) use ($participant) {
                if ($participant->last_read_at) {
                    $q->where('created_at', '>', $participant->last_read_at);
                }
            })
            ->count();
    }
}
