<?php

namespace App\Observers;

use App\Models\CommunicationConversation;
use App\Models\Conversation;
use App\Scopes\OwnerScope;

class ConversationObserver
{
    public function created(Conversation $conversation): void
    {
        $store = $conversation->store()->withoutGlobalScope(OwnerScope::class)->first();

        CommunicationConversation::firstOrCreate(
            [
                'type' => 'marketplace',
                'metadata->legacy_id' => $conversation->id,
                'metadata->table' => 'conversations',
            ],
            [
                'type' => 'marketplace',
                'title' => 'Consulta con '.($store?->title ?? 'Tienda'),
                'metadata' => [
                    'legacy_id' => $conversation->id,
                    'table' => 'conversations',
                    'buyer_id' => $conversation->buyer_id,
                    'store_profile_id' => $conversation->store_profile_id,
                    'store_user_id' => $store?->user_id,
                    'owner_id' => $conversation->owner_id,
                ],
            ]
        );
    }

    public function deleted(Conversation $conversation): void
    {
        CommunicationConversation::where('type', 'marketplace')
            ->where('metadata->legacy_id', $conversation->id)
            ->where('metadata->table', 'conversations')
            ->delete();
    }
}
