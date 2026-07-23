<?php

namespace App\Observers;

use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\MensajeConversacion;

class MensajeConversacionObserver
{
    public function created(MensajeConversacion $mensaje): void
    {
        $conversacion = $mensaje->conversacion;

        if (! $conversacion) {
            return;
        }

        $unified = CommunicationConversation::firstOrCreate(
            [
                'type' => 'order',
                'metadata->legacy_id' => $conversacion->id,
                'metadata->table' => 'conversaciones',
            ],
            [
                'type' => 'order',
                'title' => $conversacion->titulo ?? 'Pedido #'.($conversacion->pedido_id ?? $conversacion->id),
                'metadata' => [
                    'legacy_id' => $conversacion->id,
                    'table' => 'conversaciones',
                    'pedido_id' => $conversacion->pedido_id,
                    'public_profile_id' => $conversacion->public_profile_id,
                    'comprador_id' => $conversacion->comprador_id,
                    'vendedor_id' => $conversacion->vendedor_id,
                    'owner_id' => $conversacion->owner_id,
                ],
            ]
        );

        CommunicationMessage::create([
            'communication_conversation_id' => $unified->id,
            'type' => 'order',
            'sender_id' => $mensaje->sender_id,
            'receiver_id' => $mensaje->receiver_id,
            'content' => $mensaje->contenido ?? '',
            'file_path' => $mensaje->file_path,
            'read_at' => $mensaje->leido ? now() : null,
        ]);

        $unified->update(['last_message_at' => now()]);
    }

    public function updated(MensajeConversacion $mensaje): void
    {
        $unified = CommunicationConversation::where('type', 'order')
            ->where('metadata->legacy_id', $mensaje->conversacion_id)
            ->where('metadata->table', 'conversaciones')
            ->first();

        if (! $unified) {
            return;
        }

        CommunicationMessage::where('communication_conversation_id', $unified->id)
            ->where('sender_id', $mensaje->sender_id)
            ->where('created_at', $mensaje->created_at)
            ->update(['read_at' => $mensaje->leido ? $mensaje->updated_at : null]);
    }

    public function deleted(MensajeConversacion $mensaje): void
    {
        $unified = CommunicationConversation::where('type', 'order')
            ->where('metadata->legacy_id', $mensaje->conversacion_id)
            ->where('metadata->table', 'conversaciones')
            ->first();

        if (! $unified) {
            return;
        }

        CommunicationMessage::where('communication_conversation_id', $unified->id)
            ->where('sender_id', $mensaje->sender_id)
            ->where('created_at', $mensaje->created_at)
            ->delete();
    }
}
