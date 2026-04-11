<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatParticipant extends Model
{
    protected $fillable = [
        'conversation_id', 'user_id', 'role', 'is_muted', 'last_read_at', 'joined_at',
    ];

    protected $casts = [
        'is_muted'     => 'boolean',
        'last_read_at' => 'datetime',
        'joined_at'    => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
