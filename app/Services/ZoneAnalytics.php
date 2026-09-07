<?php

namespace App\Services;

use App\Models\DeliveryAsignacion;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use App\Scopes\OwnerScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ZoneAnalytics
{
    /**
     * Pedidos del tenant con coordenadas de destino, en estado activo del circuito.
     *
     * @return Collection<int, Pedido>
     */
    public function pedidosEnCircuito(): Collection
    {
        return Pedido::query()
            ->whereNotNull('destino_lat')
            ->whereNotNull('destino_lng')
            ->whereIn('estado', ['confirmado', 'preparando', 'enviado'])
            ->get(['id', 'estado', 'destino_lat', 'destino_lng']);
    }

    /**
     * Resumen de todas las zonas del tenant: capacidad, uso y repartidores.
     *
     * @return array{zonas: list<array<string, mixed>>, total_pedidos_activos: int, total_repartidores: int}
     */
    public function resumen(int $ownerId): array
    {
        return Cache::remember("zones.resumen.{$ownerId}", 60, function () {
            $pedidos = $this->pedidosEnCircuito();

            $resumenZonas = Zone::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Zone $zona) => $this->resumenZona($zona, $pedidos))
                ->values()
                ->all();

            $repartidores = Repartidor::query()->count('id');
            $repartidoresDisponibles = Repartidor::query()->where('estado', 'disponible')->count('id');

            return [
                'zonas' => $resumenZonas,
                'total_pedidos_activos' => $pedidos->count(),
                'total_repartidores' => $repartidores,
                'repartidores_disponibles' => $repartidoresDisponibles,
            ];
        });
    }

    /**
     * Métricas de una zona concreta.
     *
     * @param  Collection<int, Pedido>  $pedidos
     * @return array<string, mixed>
     */
    public function resumenZona(Zone $zona, ?Collection $pedidos = null): array
    {
        $pedidos ??= $this->pedidosEnCircuito();

        $enZona = $pedidos
            ->filter(fn (Pedido $pedido) => $zona->contiene((float) $pedido->destino_lat, (float) $pedido->destino_lng))
            ->values();

        $activos = $enZona->filter(fn (Pedido $pedido) => in_array($pedido->estado, ['preparando', 'enviado'], true));
        $capacidad = max(1, (int) $zona->capacidad_max);

        return [
            'id' => $zona->id,
            'name' => $zona->name,
            'lat' => (float) $zona->lat,
            'lng' => (float) $zona->lng,
            'radio_km' => (float) $zona->radio_km,
            'capacidad_max' => $capacidad,
            'activa' => (bool) $zona->activa,
            'pedidos_en_zona' => $enZona->count(),
            'pedidos_activos' => $activos->count(),
            'uso_percent' => round($activos->count() / $capacidad * 100, 1),
        ];
    }

    /**
     * Métricas de rendimiento sobre el histórico de asignaciones del tenant.
     *
     * @return array{
     *   dias: int,
     *   asignaciones: int,
     *   por_motivo: array<string, int>,
     *   entregadas: int,
     *   en_curso: int,
     *   tiempo_medio_pool_min: float,
     *   en_pool_sin_repartidor: int,
     *   por_dia: list<array{fecha: string, asignaciones: int}>,
     *   por_repartidor: list<array{user_id: int, nombre: string|null, asignaciones: int, entregadas: int, en_curso: int}>
     * }
     */
    public function rendimiento(int $ownerId, int $dias = 30): array
    {
        return Cache::remember("zones.rendimiento.{$ownerId}", 60, function () use ($ownerId, $dias): array {
            $desde = now()->subDays($dias);

            $asignaciones = DeliveryAsignacion::query()
                ->withoutGlobalScope(OwnerScope::class)
                ->where('owner_id', $ownerId)
                ->where('created_at', '>=', $desde)
                ->with('pedido:id,pool_entrada_at,hora_aceptado,estado')
                ->get(['id', 'pedido_id', 'repartidor_id', 'motivo', 'created_at']);

            $enCursoPorRepartidor = Pedido::query()
                ->withoutGlobalScope(OwnerScope::class)
                ->where('owner_id', $ownerId)
                ->whereIn('estado', ['preparando', 'enviado'])
                ->whereNotNull('repartidor_id')
                ->selectRaw('repartidor_id, count(*) as total')
                ->groupBy('repartidor_id')
                ->pluck('total', 'repartidor_id')
                ->all();

            $nombres = User::query()
                ->whereIn('id', $asignaciones->pluck('repartidor_id')->all())
                ->pluck('name', 'id')
                ->all();

            $porRepartidor = $asignaciones
                ->groupBy('repartidor_id')
                ->map(function (Collection $items) use ($enCursoPorRepartidor, $nombres): array {
                    $repartidorId = (int) $items->first()->repartidor_id;

                    return [
                        'user_id' => $repartidorId,
                        'nombre' => $nombres[$repartidorId] ?? null,
                        'asignaciones' => $items->count(),
                        'entregadas' => $items->filter(fn (DeliveryAsignacion $a) => $a->pedido?->estado === 'entregado')->count(),
                        'en_curso' => (int) ($enCursoPorRepartidor[$repartidorId] ?? 0),
                    ];
                })
                ->sortByDesc('asignaciones')
                ->values()
                ->all();

            $enPoolSinRepartidor = Pedido::query()
                ->withoutGlobalScope(OwnerScope::class)
                ->where('owner_id', $ownerId)
                ->where('estado', 'preparando')
                ->whereNull('repartidor_id')
                ->whereNotNull('destino_lat')
                ->whereNotNull('destino_lng')
                ->count('id');

            $tiemposPool = $asignaciones
                ->map(fn (DeliveryAsignacion $a) => $a->pedido)
                ->filter(fn (?Pedido $pedido) => $pedido !== null
                    && $pedido->pool_entrada_at !== null
                    && $pedido->hora_aceptado !== null)
                ->map(fn (Pedido $pedido) => $pedido->pool_entrada_at->diffInSeconds($pedido->hora_aceptado));

            $porDia = $asignaciones
                ->groupBy(fn (DeliveryAsignacion $a) => $a->created_at->toDateString())
                ->map(fn (Collection $items, string $fecha) => [
                    'fecha' => $fecha,
                    'asignaciones' => $items->count(),
                ])
                ->sortBy('fecha')
                ->take(14)
                ->values()
                ->all();

            $entregadas = $asignaciones->filter(fn (DeliveryAsignacion $a) => $a->pedido?->estado === 'entregado')->count();
            $enCurso = $asignaciones->filter(fn (DeliveryAsignacion $a) => in_array($a->pedido?->estado, ['preparando', 'enviado'], true))->count();

            return [
                'dias' => $dias,
                'asignaciones' => $asignaciones->count(),
                'por_motivo' => $asignaciones->countBy('motivo')->all(),
                'entregadas' => $entregadas,
                'en_curso' => $enCurso,
                'tiempo_medio_pool_min' => round($tiemposPool->avg() / 60, 1),
                'en_pool_sin_repartidor' => $enPoolSinRepartidor,
                'por_dia' => $porDia,
                'por_repartidor' => $porRepartidor,
            ];
        });
    }

    /**
     * Limpia la caché del resumen para que la siguiente petición sea fresca.
     */
    public function olvidarResumen(int $ownerId): void
    {
        Cache::forget("zones.resumen.{$ownerId}");
    }
}
