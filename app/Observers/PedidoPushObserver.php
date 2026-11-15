<?php

namespace App\Observers;

use App\Models\Pedido;
use App\Scopes\OwnerScope;
use App\Services\DeliveryPushService;
use Illuminate\Support\Facades\DB;

class PedidoPushObserver
{
    public function saved(Pedido $pedido): void
    {
        if ($pedido->wasRecentlyCreated || $pedido->wasChanged(['estado', 'repartidor_id', 'hora_aceptado', 'pool_entrada_at', 'pool_visible_at', 'pool_bloqueado'])) {
            $pedidoId = $pedido->id;
            DB::afterCommit(function () use ($pedidoId): void {
                $current = Pedido::withoutGlobalScope(OwnerScope::class)->find($pedidoId);
                if ($current) {
                    app(DeliveryPushService::class)->queueAlerts($current);
                }
            });
        }
    }
}
