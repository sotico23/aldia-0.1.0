<?php

namespace App\Services;

use App\Events\DeliveryOrderPoolUpdated;
use App\Models\DeliveryConfig;
use App\Models\Pedido;
use App\Models\PedidoStatusLog;
use App\Scopes\OwnerScope;

class PedidoPoolService
{
    /**
     * Pone un pedido en el pool cuando el vendedor lo pasa a preparando:
     * registra la entrada, resetea bloqueos del ciclo anterior y emite broadcast.
     */
    public function ingresarAlPool(Pedido $pedido): void
    {
        if ($pedido->estado !== 'preparando' || $pedido->repartidor_id !== null || $pedido->hora_aceptado !== null) {
            return;
        }

        $pedido->update([
            'pool_entrada_at' => $pedido->pool_entrada_at ?? now(),
            'pool_bloqueado' => false,
        ]);

        $pedido->refresh();
        broadcast(new DeliveryOrderPoolUpdated($pedido, 'nuevo'));
    }

    /**
     * Devuelve un pedido al pool tras un rechazo/timeout/liberacion. Respeta
     * el limite de reenvios (pool_reenvios_max) y el cooldown de reingreso
     * (pool_reenvio_min) configurados por tenant.
     *
     * @return string 'reenviado' | 'bloqueado'
     */
    public function devolverAlPool(
        Pedido $pedido,
        string $motivo,
        ?int $changedBy = null,
        bool $contarReenvio = true,
        ?string $motivoLog = null,
    ): string {
        if ($pedido->pool_bloqueado) {
            return 'bloqueado';
        }

        $config = DeliveryConfig::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $pedido->owner_id)
            ->first();

        $reenvios = $pedido->pool_reenvios + ($contarReenvio ? 1 : 0);
        $maxReenvios = $config?->pool_reenvios_max;
        $bloqueado = $maxReenvios !== null && $contarReenvio && $reenvios >= $maxReenvios;
        $repartidorAnterior = $pedido->repartidor_id;
        $motivoLog = $motivoLog ?? $motivo;

        $pedido->update([
            'repartidor_id' => null,
            'hora_aceptado' => null,
            'pool_reenvios' => $reenvios,
            'pool_entrada_at' => now(),
            'pool_bloqueado' => $bloqueado,
            'pool_visible_at' => $bloqueado ? null : now()->addMinutes($config?->pool_reenvio_min ?? 30),
        ]);

        PedidoStatusLog::create([
            'pedido_id' => $pedido->id,
            'field' => 'repartidor',
            'from' => $repartidorAnterior !== null ? (string) $repartidorAnterior : 'pool',
            'to' => $bloqueado ? 'bloqueado' : 'pool',
            'changed_by' => $changedBy,
            'motivo' => $bloqueado ? 'bloqueado_'.$motivoLog : $motivoLog,
        ]);

        $pedido->refresh();
        broadcast(new DeliveryOrderPoolUpdated($pedido, $bloqueado ? 'bloqueado' : $motivo));

        return $bloqueado ? 'bloqueado' : 'reenviado';
    }
}
