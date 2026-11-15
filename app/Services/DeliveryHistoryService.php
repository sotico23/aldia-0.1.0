<?php

namespace App\Services;

use App\Models\Entrega;
use App\Models\Pedido;
use Illuminate\Pagination\LengthAwarePaginator;

class DeliveryHistoryService
{
    public function paginateForOwner(int $ownerId): LengthAwarePaginator
    {
        return Entrega::query()
            ->where('owner_id', $ownerId)
            ->where('estado', 'entregado')
            ->with([
                'pedido' => fn ($query) => $query->where('owner_id', $ownerId)->with('repartidor:id,name'),
                'repartidor:id,user_id',
                'repartidor.user:id,name',
                'conductor:id,nombre',
            ])
            ->orderByDesc(Pedido::select('hora_entregado')->whereColumn('pedidos.id', 'entregas.pedido_id'))
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'registro_page')
            ->withQueryString()
            ->through(fn (Entrega $entrega): array => [
                'id' => $entrega->id,
                'pedido_number' => $entrega->pedido?->numero_pedido,
                'cliente' => $entrega->getRawOriginal('cliente'),
                'direccion' => $entrega->direccion,
                'driver_name' => $entrega->pedido?->repartidor?->name
                    ?? $entrega->repartidor?->user?->name
                    ?? $entrega->conductor?->nombre,
                'accepted_at' => $entrega->pedido?->hora_aceptado?->toISOString(),
                'picked_up_at' => $entrega->pedido?->hora_recogido?->toISOString(),
                'delivered_at' => $entrega->pedido?->hora_entregado?->toISOString(),
                'has_photo' => $entrega->pedido?->delivery_photo_path !== null && $entrega->pedido !== null,
            ]);
    }
}
