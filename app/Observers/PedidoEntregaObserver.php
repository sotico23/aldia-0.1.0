<?php

namespace App\Observers;

use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Scopes\OwnerScope;

class PedidoEntregaObserver
{
    public function updated(Pedido $pedido): void
    {
        if (! $pedido->wasChanged(['estado', 'repartidor_id', 'hora_aceptado'])) {
            return;
        }

        $repartidorId = $pedido->repartidor_id
            ? Repartidor::withoutGlobalScope(OwnerScope::class)
                ->where('user_id', $pedido->repartidor_id)->value('id')
            : null;

        $attributes = [
            'estado' => match ($pedido->estado) {
                'enviado' => 'en_ruta',
                'entregado' => 'entregado',
                'cancelado' => 'cancelado',
                default => 'pendiente',
            },
        ];

        if ($pedido->wasChanged('repartidor_id')) {
            $attributes['repartidor_id'] = $repartidorId;
            $attributes['estado_asignacion'] = $repartidorId ? 'asignado' : 'libre';
        }

        Entrega::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $pedido->owner_id)->where('pedido_id', $pedido->id)
            ->update($attributes);
    }
}
