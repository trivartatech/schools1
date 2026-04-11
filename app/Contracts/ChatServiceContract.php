<?php

namespace App\Contracts;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Support\Collection;

interface ChatServiceContract
{
    public function getOrCreateDirectConversation(User $userA, User $userB): ChatConversation;

    public function sendMessage(ChatConversation $conversation, User $sender, string $body, ?string $type = 'text', array $meta = []): mixed;

    public function getConversationsForUser(User $user): Collection;

    public function markAsRead(ChatConversation $conversation, User $user): void;

    public function getUnreadCount(User $user): int;
}
