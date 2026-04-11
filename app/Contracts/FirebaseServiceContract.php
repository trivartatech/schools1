<?php

namespace App\Contracts;

use App\Models\User;

interface FirebaseServiceContract
{
    public function isEnabled(): bool;

    public function sendToUser(User $user, string $title, string $body, array $data = []): bool;

    public function sendToToken(string $token, string $title, string $body, array $data = []): bool;

    public function sendToUsers($users, string $title, string $body, array $data = []): int;

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): int;
}
