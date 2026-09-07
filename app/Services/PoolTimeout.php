<?php

namespace App\Services;

use App\Models\DeliveryConfig;
use App\Models\Pedido;
use App\Scopes\OwnerScope;
use Carbon\CarbonImmutable;

class PoolTimeout
{
    public function __construct(private readonly PedidoPoolService $pedidoPool) {}

    /**
     * Libera los pedidos aceptados pero no recogidos dentro del
     * pool_timeout_min configurado por tenant (default: 10 min).
     *
     * @return array{procesados: int, liberados: int}
     */
    public function liberarVencidos(): array
    {
        $candidatos = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('estado', 'preparando')
            ->whereNotNull('repartidor_id')
            ->whereNotNull('hora_aceptado')
            ->whereNull('hora_recogido')
            ->get();

        $stats = ['procesados' => $candidatos->count(), 'liberados' => 0];

        foreach ($candidatos->groupBy('owner_id') as $ownerId => $pedidos) {
            $timeoutMin = $this->timeoutParaTenant((int) $ownerId);

            foreach ($pedidos as $pedido) {
                if ($pedido->hora_aceptado->lte(CarbonImmutable::now()->subMinutes($timeoutMin))) {
                    $this->pedidoPool->devolverAlPool($pedido, 'timeout');
                    $stats['liberados']++;
                }
            }
        }

        return $stats;
    }

    private function timeoutParaTenant(int $ownerId): int
    {
        $config = DeliveryConfig::query()->where('owner_id', $ownerId)->first();

        return $config ? max(1, (int) $config->pool_timeout_min) : 10;
    }
}
