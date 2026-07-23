<?php

namespace App\Contracts;

use App\Models\User;

interface HasMessageThread
{
    public function type(): string;

    public function participants(): array;

    public function otherParticipant(User $user): ?User;

    public function markAsRead(User $user): int;

    public function unreadCount(User $user): int;

    public function latestMessageContent(): ?string;

    public function lastMessageAt(): mixed;
}
