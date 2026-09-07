<?php

namespace App\Services;

use App\Events\DeliveryOrderPoolUpdated;
use App\Helpers\NotificationHelper;
use App\Models\DeliveryAsignacion;
use App\Models\DeliveryConfig;
use App\Models\Pedido;
use App\Models\PedidoStatusLog;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\DeliveryPedidoAceptadoNotification;
use App\Scopes\OwnerScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ZoneAssignment
{
    /**
     * Procesa todos los tenants: asigna pedidos del pool a la mejor zona
     * (capacidad + proximidad) y al repartidor mas cercano dentro de ella.
     *
     * @return array{procesados: int, asignados: int, sin_zona: int, zona_llena: int, sin_repartidor: int}
     */
    public function assignPending(): array
    {
        $stats = [
            'procesados' => 0,
            'asignados' => 0,
            'sin_zona' => 0,
            'zona_llena' => 0,
            'sin_repartidor' => 0,
        ];

        $pedidosPool = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->disponibleEnPool()
            ->whereNotNull('destino_lat')
            ->whereNotNull('destino_lng')
            ->orderBy('created_at')
            ->get();

        foreach ($pedidosPool->groupBy('owner_id') as $ownerId => $pedidos) {
            $this->assignForOwner((int) $ownerId, $pedidos, $stats);
        }

        return $stats;
    }

    /**
     * @param  Collection<int, Pedido>  $pedidos
     * @param  array<string, int>  $stats
     */
    private function assignForOwner(int $ownerId, Collection $pedidos, array &$stats): void
    {
        $config = DeliveryConfig::query()->where('owner_id', $ownerId)->first();

        if ($config && $config->modo === 'manual') {
            return;
        }

        $zonas = Zone::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->activa()
            ->orderBy('radio_km', 'desc')
            ->get();

        if ($zonas->isEmpty()) {
            $stats['sin_zona'] += $pedidos->count();

            return;
        }

        $repartidores = Repartidor::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->where('estado', 'disponible')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get();

        if ($repartidores->isEmpty()) {
            $stats['sin_repartidor'] += $pedidos->count();

            return;
        }

        $capacidadUsada = $this->capacidadUsadaPorZona($ownerId, $zonas);
        $capacidadRestante = $this->capacidadRestantePorRepartidor($repartidores);

        foreach ($pedidos as $pedido) {
            $stats['procesados']++;

            $zona = $this->zonaParaPedido($zonas, (float) $pedido->destino_lat, (float) $pedido->destino_lng);

            if (! $zona) {
                $stats['sin_zona']++;

                continue;
            }

            $capacidadUsada[$zona->id] = ($capacidadUsada[$zona->id] ?? 0) + 1;

            if ($capacidadUsada[$zona->id] > (int) $zona->capacidad_max) {
                $stats['zona_llena']++;

                continue;
            }

            $repartidor = $this->repartidorMasCercano(
                $repartidores,
                (float) $pedido->destino_lat,
                (float) $pedido->destino_lng,
                $capacidadRestante
            );

            if (! $repartidor) {
                $stats['sin_repartidor']++;

                continue;
            }

            if (! $this->asignarPedido($pedido, $repartidor, 'auto_asignado', null, $zona)) {
                continue;
            }

            $capacidadRestante[$repartidor->user_id] = ($capacidadRestante[$repartidor->user_id] ?? 1) - 1;
            $stats['asignados']++;
        }
    }

    /**
     * Pedidos en circuito (preparando/enviado) dentro de cada zona.
     *
     * @param  Collection<int, Zone>  $zonas
     * @return array<int, int> id de zona => conteo
     */
    private function capacidadUsadaPorZona(int $ownerId, Collection $zonas): array
    {
        $pedidos = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->whereIn('estado', ['preparando', 'enviado'])
            ->whereNotNull('repartidor_id')
            ->whereNotNull('destino_lat')
            ->whereNotNull('destino_lng')
            ->get(['id', 'estado', 'destino_lat', 'destino_lng']);

        $usada = [];

        foreach ($zonas as $zona) {
            $usada[$zona->id] = $pedidos
                ->filter(fn (Pedido $pedido) => $zona->contiene((float) $pedido->destino_lat, (float) $pedido->destino_lng))
                ->count();
        }

        return $usada;
    }

    /**
     * Capacidad restante de cada repartidor (capacidad_max menos pedidos en
     * curso previos a este run).
     *
     * @param  Collection<int, Repartidor>  $repartidores
     * @return array<int, int> user_id => cupos disponibles
     */
    private function capacidadRestantePorRepartidor(Collection $repartidores): array
    {
        $activos = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->whereIn('repartidor_id', $repartidores->pluck('user_id')->all())
            ->whereIn('estado', ['preparando', 'enviado'])
            ->selectRaw('repartidor_id, count(*) as total')
            ->groupBy('repartidor_id')
            ->pluck('total', 'repartidor_id')
            ->all();

        return $repartidores
            ->mapWithKeys(fn (Repartidor $repartidor) => [
                $repartidor->user_id => max(0, (int) $repartidor->capacidad_max - (int) ($activos[$repartidor->user_id] ?? 0)),
            ])
            ->all();
    }

    /**
     * Zona mas cercana que contiene el destino del pedido.
     *
     * @param  Collection<int, Zone>  $zonas
     */
    private function zonaParaPedido(Collection $zonas, float $lat, float $lng): ?Zone
    {
        return $zonas
            ->sortBy(fn (Zone $zona) => $zona->distanciaA($lat, $lng))
            ->first(fn (Zone $zona) => $zona->contiene($lat, $lng));
    }

    /**
     * Repartidor disponible mas cercano al destino, dentro de su radio y con
     * cupo de capacidad libre.
     *
     * @param  Collection<int, Repartidor>  $repartidores
     * @param  array<int, int>  $capacidadRestante
     */
    private function repartidorMasCercano(
        Collection $repartidores,
        float $lat,
        float $lng,
        array $capacidadRestante = []
    ): ?Repartidor {
        return $repartidores
            ->filter(fn (Repartidor $repartidor) => $repartidor->radio_km !== null
                && ($repartidor->distanciaA($lat, $lng) ?? INF) <= (float) $repartidor->radio_km
                && (($capacidadRestante[$repartidor->user_id] ?? 1) > 0))
            ->sortBy(fn (Repartidor $repartidor) => $repartidor->distanciaA($lat, $lng) ?? INF)
            ->first();
    }

    /**
     * Asignacion manual de un pedido del pool (accion de administracion):
     * a un repartidor concreto, o al mejor repartidor disponible dentro de
     * una zona (capacidad y proximidad). Respeta cooldown y bloqueos.
     *
     * @return array{success: bool, motivo: string, repartidor_id: int|null}
     */
    public function asignarUnPedido(Pedido $pedido, ?Repartidor $repartidor, ?Zone $zona, ?int $asignadorId = null): array
    {
        $enPool = $pedido->estado === 'preparando'
            && $pedido->repartidor_id === null
            && $pedido->hora_aceptado === null
            && ! $pedido->pool_bloqueado
            && ($pedido->pool_visible_at === null || ! $pedido->pool_visible_at->isFuture());

        if (! $enPool) {
            return ['success' => false, 'motivo' => 'no_en_pool', 'repartidor_id' => null];
        }

        if ($zona !== null) {
            if ($pedido->destino_lat === null || $pedido->destino_lng === null) {
                return ['success' => false, 'motivo' => 'sin_coordenadas', 'repartidor_id' => null];
            }

            if (! $zona->contiene((float) $pedido->destino_lat, (float) $pedido->destino_lng)) {
                return ['success' => false, 'motivo' => 'fuera_de_zona', 'repartidor_id' => null];
            }

            $usada = $this->capacidadUsadaPorZona((int) $pedido->owner_id, collect([$zona]));

            if (($usada[$zona->id] ?? 0) >= (int) $zona->capacidad_max) {
                return ['success' => false, 'motivo' => 'zona_llena', 'repartidor_id' => null];
            }

            if ($repartidor === null) {
                $repartidor = $this->repartidorMasCercano(
                    Repartidor::query()
                        ->withoutGlobalScope(OwnerScope::class)
                        ->where('owner_id', $pedido->owner_id)
                        ->where('estado', 'disponible')
                        ->whereNotNull('lat')
                        ->whereNotNull('lng')
                        ->get(),
                    (float) $pedido->destino_lat,
                    (float) $pedido->destino_lng
                );
            }
        }

        if (! $repartidor) {
            return ['success' => false, 'motivo' => 'sin_repartidor', 'repartidor_id' => null];
        }

        $activos = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $pedido->owner_id)
            ->where('repartidor_id', $repartidor->user_id)
            ->whereIn('estado', ['preparando', 'enviado'])
            ->count();

        if ($activos >= (int) $repartidor->capacidad_max) {
            return ['success' => false, 'motivo' => 'repartidor_ocupado', 'repartidor_id' => null];
        }

        if (! $this->asignarPedido($pedido, $repartidor, 'asignado_manual', $asignadorId, $zona)) {
            return ['success' => false, 'motivo' => 'no_en_pool', 'repartidor_id' => null];
        }

        return ['success' => true, 'motivo' => 'asignado_manual', 'repartidor_id' => $repartidor->id];
    }

    /**
     * Asignacion automatica de un pedido concreto (job de cola): solo si hay
     * zona activa con cupo, repartidor con capacidad dentro del radio y el
     * pedido sigue en el pool. Si no es posible, el scheduler lo reintenta.
     *
     * @return array{asignado: bool, motivo: string, repartidor_id: int|null}
     */
    public function asignarSiPosible(Pedido $pedido): array
    {
        if ($pedido->estado !== 'preparando' || $pedido->repartidor_id !== null || $pedido->hora_aceptado !== null) {
            return ['asignado' => false, 'motivo' => 'no_en_pool', 'repartidor_id' => null];
        }

        $config = DeliveryConfig::query()->where('owner_id', $pedido->owner_id)->first();

        if ($config && $config->modo === 'manual') {
            return ['asignado' => false, 'motivo' => 'modo_manual', 'repartidor_id' => null];
        }

        if ($pedido->destino_lat === null || $pedido->destino_lng === null) {
            return ['asignado' => false, 'motivo' => 'sin_coordenadas', 'repartidor_id' => null];
        }

        $zonas = Zone::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $pedido->owner_id)
            ->activa()
            ->orderBy('radio_km', 'desc')
            ->get();

        $zona = $this->zonaParaPedido($zonas, (float) $pedido->destino_lat, (float) $pedido->destino_lng);

        if (! $zona) {
            return ['asignado' => false, 'motivo' => 'sin_zona', 'repartidor_id' => null];
        }

        $usada = $this->capacidadUsadaPorZona((int) $pedido->owner_id, collect([$zona]));

        if (($usada[$zona->id] ?? 0) >= (int) $zona->capacidad_max) {
            return ['asignado' => false, 'motivo' => 'zona_llena', 'repartidor_id' => null];
        }

        $repartidores = Repartidor::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $pedido->owner_id)
            ->where('estado', 'disponible')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get();

        $repartidor = $this->repartidorMasCercano(
            $repartidores,
            (float) $pedido->destino_lat,
            (float) $pedido->destino_lng,
            $this->capacidadRestantePorRepartidor($repartidores)
        );

        if (! $repartidor) {
            return ['asignado' => false, 'motivo' => 'sin_repartidor', 'repartidor_id' => null];
        }

        if ($pedido->pool_bloqueado || $pedido->pool_visible_at !== null && $pedido->pool_visible_at->isFuture()) {
            return ['asignado' => false, 'motivo' => 'en_cooldown', 'repartidor_id' => null];
        }

        if (! $this->asignarPedido($pedido, $repartidor, 'auto_asignado', null, $zona)) {
            return ['asignado' => false, 'motivo' => 'no_en_pool', 'repartidor_id' => null];
        }

        return ['asignado' => true, 'motivo' => 'auto_asignado', 'repartidor_id' => $repartidor->id];
    }

    /**
     * Asigna el pedido al repartidor de forma atomica: bloquea la fila del
     * pedido y revalida que siga en el pool antes de ocuparla. Devuelve false
     * si otro proceso (scheduler, job o rechazo concurrente) ya lo tomo,
     * evitando dobles entregas entre el job de cola y el tick.
     */
    public function asignarPedido(
        Pedido $pedido,
        Repartidor $repartidor,
        string $motivo = 'auto_asignado',
        ?int $asignadorId = null,
        ?Zone $zona = null,
    ): bool {
        $asignado = DB::transaction(function () use ($pedido, $repartidor, $motivo, $asignadorId, $zona): bool {
            $bloqueado = Pedido::query()
                ->withoutGlobalScope(OwnerScope::class)
                ->whereKey($pedido->id)
                ->lockForUpdate()
                ->first();

            if (! $bloqueado || $bloqueado->repartidor_id !== null || $bloqueado->hora_aceptado !== null) {
                return false;
            }

            $bloqueado->update([
                'repartidor_id' => $repartidor->user_id,
                'hora_aceptado' => now(),
            ]);

            broadcast(new DeliveryOrderPoolUpdated($bloqueado, $motivo));

            PedidoStatusLog::create([
                'pedido_id' => $bloqueado->id,
                'field' => 'repartidor',
                'from' => 'pool',
                'to' => (string) $repartidor->user_id,
                'changed_by' => $asignadorId,
                'motivo' => $motivo,
            ]);

            DeliveryAsignacion::registrar($bloqueado, (int) $repartidor->user_id, $zona, $asignadorId, $motivo);

            $bloqueado->load('conversacion');
            $vendedorId = $bloqueado->conversacion?->vendedor_id ?? $bloqueado->user_id;
            $vendedor = $vendedorId ? User::find($vendedorId) : null;

            if ($vendedor) {
                $repartidor->load('user');
                NotificationHelper::send($vendedor, new DeliveryPedidoAceptadoNotification($bloqueado, $repartidor));
            }

            return true;
        });

        if ($asignado) {
            $pedido->refresh();
        }

        return $asignado;
    }
}
