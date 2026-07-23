<?php

namespace App\Policies;

use App\Models\CommunicationConversation;
use App\Models\User;

class CommunicationConversationPolicy
{
    public function view(User $user, CommunicationConversation $conversation): bool
    {
        return match ($conversation->type) {
            'order' => in_array($user->id, [
                $conversation->metadata['comprador_id'] ?? null,
                $conversation->metadata['vendedor_id'] ?? null,
            ]),
            'marketplace' => in_array($user->id, [
                $conversation->metadata['buyer_id'] ?? null,
                $conversation->metadata['store_user_id'] ?? null,
            ]),
            'internal' => in_array($user->id, [
                $conversation->metadata['user_a_id'] ?? null,
                $conversation->metadata['user_b_id'] ?? null,
            ]),
            default => false,
        };
    }

    public function sendMessage(User $user, CommunicationConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
