<?php

namespace App\Observers;

use App\Models\CommunicationConversation;
use App\Models\Conversacion;

class ConversacionObserver
{
    public function created(Conversacion $conversacion): void
    {
        CommunicationConversation::firstOrCreate(
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
    }

    public function updated(Conversacion $conversacion): void
    {
        CommunicationConversation::where('type', 'order')
            ->where('metadata->legacy_id', $conversacion->id)
            ->where('metadata->table', 'conversaciones')
            ->update([
                'title' => $conversacion->titulo ?? 'Pedido #'.($conversacion->pedido_id ?? $conversacion->id),
                'last_message_at' => $conversacion->ultimo_mensaje_at,
            ]);
    }

    public function deleted(Conversacion $conversacion): void
    {
        CommunicationConversation::where('type', 'order')
            ->where('metadata->legacy_id', $conversacion->id)
            ->where('metadata->table', 'conversaciones')
            ->delete();
    }
}
