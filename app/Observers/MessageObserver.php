<?php

namespace App\Observers;

use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\Message;
use App\Scopes\OwnerScope;

class MessageObserver
{
    public function created(Message $message): void
    {
        $conversation = $message->conversation;

        if (! $conversation) {
            return;
        }

        $store = $conversation->store()->withoutGlobalScope(OwnerScope::class)->first();

        $unified = CommunicationConversation::firstOrCreate(
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

        $receiverId = $message->sender_id === $conversation->buyer_id
            ? ($store?->user_id ?? $conversation->owner_id)
            : $conversation->buyer_id;

        CommunicationMessage::create([
            'communication_conversation_id' => $unified->id,
            'type' => 'marketplace',
            'sender_id' => $message->sender_id,
            'receiver_id' => $receiverId,
            'content' => $message->body ?? '',
            'file_path' => $message->image_path,
            'read_at' => $message->read_at,
        ]);

        $unified->update(['last_message_at' => now()]);
    }

    public function updated(Message $message): void
    {
        $unified = CommunicationConversation::where('type', 'marketplace')
            ->where('metadata->legacy_id', $message->conversation_id)
            ->where('metadata->table', 'conversations')
            ->first();

        if (! $unified) {
            return;
        }

        CommunicationMessage::where('communication_conversation_id', $unified->id)
            ->where('sender_id', $message->sender_id)
            ->where('created_at', $message->created_at)
            ->update(['read_at' => $message->read_at]);
    }

    public function deleted(Message $message): void
    {
        $unified = CommunicationConversation::where('type', 'marketplace')
            ->where('metadata->legacy_id', $message->conversation_id)
            ->where('metadata->table', 'conversations')
            ->first();

        if (! $unified) {
            return;
        }

        CommunicationMessage::where('communication_conversation_id', $unified->id)
            ->where('sender_id', $message->sender_id)
            ->where('created_at', $message->created_at)
            ->delete();
    }
}
