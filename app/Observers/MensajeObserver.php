<?php

namespace App\Observers;

use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\Mensaje;

class MensajeObserver
{
    public function created(Mensaje $mensaje): void
    {
        $userA = min($mensaje->sender_id, $mensaje->receiver_id);
        $userB = max($mensaje->sender_id, $mensaje->receiver_id);
        $pairKey = $userA.'-'.$userB;

        $unified = CommunicationConversation::firstOrCreate(
            [
                'type' => 'internal',
                'metadata->pair_key' => $pairKey,
            ],
            [
                'type' => 'internal',
                'title' => 'Chat interno',
                'metadata' => [
                    'user_a_id' => $userA,
                    'user_b_id' => $userB,
                    'pair_key' => $pairKey,
                ],
            ]
        );

        CommunicationMessage::create([
            'communication_conversation_id' => $unified->id,
            'type' => 'internal',
            'sender_id' => $mensaje->sender_id,
            'receiver_id' => $mensaje->receiver_id,
            'content' => $mensaje->contenido ?? '',
            'file_path' => $mensaje->archivo_path,
            'file_name' => $mensaje->archivo_nombre,
            'file_type' => $mensaje->archivo_tipo,
            'read_at' => $mensaje->leido ? now() : null,
        ]);

        $unified->update(['last_message_at' => now()]);
    }

    public function updated(Mensaje $mensaje): void
    {
        $userA = min($mensaje->sender_id, $mensaje->receiver_id);
        $userB = max($mensaje->sender_id, $mensaje->receiver_id);
        $pairKey = $userA.'-'.$userB;

        $unified = CommunicationConversation::where('type', 'internal')
            ->where('metadata->pair_key', $pairKey)
            ->first();

        if (! $unified) {
            return;
        }

        CommunicationMessage::where('communication_conversation_id', $unified->id)
            ->where('sender_id', $mensaje->sender_id)
            ->where('receiver_id', $mensaje->receiver_id)
            ->where('created_at', $mensaje->created_at)
            ->update(['read_at' => $mensaje->leido ? $mensaje->updated_at : null]);
    }

    public function deleted(Mensaje $mensaje): void
    {
        $userA = min($mensaje->sender_id, $mensaje->receiver_id);
        $userB = max($mensaje->sender_id, $mensaje->receiver_id);
        $pairKey = $userA.'-'.$userB;

        $unified = CommunicationConversation::where('type', 'internal')
            ->where('metadata->pair_key', $pairKey)
            ->first();

        if (! $unified) {
            return;
        }

        CommunicationMessage::where('communication_conversation_id', $unified->id)
            ->where('sender_id', $mensaje->sender_id)
            ->where('receiver_id', $mensaje->receiver_id)
            ->where('created_at', $mensaje->created_at)
            ->delete();
    }
}
