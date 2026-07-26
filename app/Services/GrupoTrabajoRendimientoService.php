<?php

namespace App\Services;

use App\Models\Entrega;
use App\Models\EntregaItem;
use App\Models\GrupoTrabajo;
use App\Models\GrupoTrabajoAsignacion;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GrupoTrabajoRendimientoService
{
    public function calcularPorGrupos(
        int $ownerId,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        ?array $grupoIds = null,
    ): array {
        $query = GrupoTrabajo::with('conductores')
            ->where('owner_id', $ownerId)
            ->where('estado', 'activo');

        if ($grupoIds !== null) {
            $query->whereIn('id', $grupoIds);
        }

        $grupos = $query->get();

        $totalMonto = 0;
        $totalCantidad = 0;
        $totalKg = 0;
        $totalL = 0;
        $porGrupo = [];

        foreach ($grupos as $grupo) {
            $metrics = $this->calcularMetricasGrupo($grupo, $ownerId, $fechaInicio, $fechaFin);

            $totalMonto += $metrics['monto'];
            $totalCantidad += $metrics['cantidad'];
            $totalKg += $metrics['kg'];
            $totalL += $metrics['l'];

            $porGrupo[] = [
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'color' => $grupo->color,
                'monto' => $metrics['monto'],
                'cantidad' => $metrics['cantidad'],
                'kg' => $metrics['kg'],
                'l' => $metrics['l'],
            ];
        }

        return [
            'totalMonto' => $totalMonto,
            'totalCantidad' => $totalCantidad,
            'totalKg' => $totalKg,
            'totalL' => $totalL,
            'porGrupo' => $porGrupo,
        ];
    }

    public function calcularMetricasGrupo(
        GrupoTrabajo $grupo,
        int $ownerId,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
    ): array {
        $conductorIds = $grupo->conductores->pluck('id');

        $ventaQuery = Entrega::where(function ($q) use ($conductorIds, $grupo) {
            $q->whereIn('conductor_id', $conductorIds)
                ->orWhere('grupo_trabajo_id', $grupo->id);
        })
            ->where('owner_id', $ownerId)
            ->whereHas('venta', function ($q) use ($fechaInicio, $fechaFin) {
                $q->where('estado', 'pagada');

                if ($fechaInicio && $fechaFin) {
                    $q->whereBetween('fecha', [$fechaInicio, $fechaFin]);
                }
            });

        $ventaIds = $ventaQuery->pluck('venta_id');

        $monto = (float) Venta::whereIn('id', $ventaIds)->sum('total');
        $cantidad = count($ventaIds);

        [$kg, $l] = $this->calcularKgL($ventaIds);

        return [
            'monto' => $monto,
            'cantidad' => $cantidad,
            'kg' => $kg,
            'l' => $l,
        ];
    }

    public function calcularTendenciaMensual(int $ownerId, int $meses): array
    {
        $tendencia = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $fecha = Carbon::now()->subMonths($i);
            $inicio = $fecha->copy()->startOfMonth()->toDateString();
            $fin = $fecha->copy()->endOfMonth()->toDateString();

            $rendimiento = $this->calcularPorGrupos($ownerId, $inicio, $fin);

            $tendencia[] = [
                'mes' => $fecha->isoFormat('MMM YY'),
                'anio' => $fecha->format('Y'),
                'mes_num' => $fecha->format('m'),
                'monto' => $rendimiento['totalMonto'],
                'cantidad' => $rendimiento['totalCantidad'],
                'kg' => $rendimiento['totalKg'],
                'l' => $rendimiento['totalL'],
            ];
        }

        return $tendencia;
    }

    public function calcularComparativaGrupos(
        int $ownerId,
        string $fechaInicio,
        string $fechaFin,
        ?array $grupoIds = null,
    ): array {
        $query = GrupoTrabajo::with('conductores')
            ->where('owner_id', $ownerId)
            ->where('estado', 'activo');

        if ($grupoIds !== null) {
            $query->whereIn('id', $grupoIds);
        }

        return $query->get()->map(function ($grupo) use ($ownerId, $fechaInicio, $fechaFin) {
            $metrics = $this->calcularMetricasGrupo($grupo, $ownerId, $fechaInicio, $fechaFin);

            return [
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'color' => $grupo->color,
                'monto' => $metrics['monto'],
                'cantidad' => $metrics['cantidad'],
                'kg' => $metrics['kg'],
                'l' => $metrics['l'],
            ];
        })->toArray();
    }

    private function calcularKgL(Collection $ventaIds): array
    {
        $totalKg = 0.0;
        $totalL = 0.0;

        if ($ventaIds->isEmpty()) {
            return [0.0, 0.0];
        }

        $entregaIds = Entrega::whereIn('venta_id', $ventaIds)->pluck('id');

        if ($entregaIds->isEmpty()) {
            return [0.0, 0.0];
        }

        $items = EntregaItem::whereIn('entrega_id', $entregaIds)->get();

        foreach ($items as $item) {
            $unidad = strtolower($item->unidad_medida ?? '');

            if ($unidad === 'kg') {
                $totalKg += (float) ($item->subtotal_metrica ?? 0);
            } elseif ($unidad === 'l' || $unidad === 'lt' || $unidad === 'litro' || $unidad === 'litros') {
                $totalL += (float) ($item->subtotal_metrica ?? 0);
            }
        }

        return [$totalKg, $totalL];
    }

    public function calcularCumplimientoAsignacion(GrupoTrabajoAsignacion $asignacion): array
    {
        $ownerId = $asignacion->owner_id;
        $grupo = $asignacion->grupoTrabajo;

        $conductorIds = $grupo->conductores->pluck('id');

        $entregas = Entrega::where(function ($q) use ($conductorIds, $grupo) {
            $q->whereIn('conductor_id', $conductorIds)
                ->orWhere('grupo_trabajo_id', $grupo->id);
        })
            ->where('owner_id', $ownerId)
            ->whereHas('venta', function ($q) use ($asignacion) {
                $q->where('estado', 'pagada')
                    ->whereBetween('fecha', [$asignacion->fecha_inicio, $asignacion->fecha_fin]);
            })
            ->pluck('id');

        $pesoEntregadoKg = 0.0;

        if ($entregas->isNotEmpty()) {
            $pesoEntregadoKg = EntregaItem::whereIn('entrega_id', $entregas)
                ->where('unidad_medida', 'kg')
                ->sum('subtotal_metrica');
        }

        $metaKg = (float) $asignacion->meta_kg;
        $porcentajeCumplimientoKg = $metaKg > 0 ? round(($pesoEntregadoKg / $metaKg) * 100, 2) : 0.0;

        return [
            'peso_entregado_kg' => (float) $pesoEntregadoKg,
            'meta_kg' => $metaKg,
            'porcentaje_cumplimiento_kg' => $porcentajeCumplimientoKg,
            'cumple_meta_kg' => $pesoEntregadoKg >= $metaKg,
        ];
    }

    public function asignacionesActivas(int $ownerId): Collection
    {
        return GrupoTrabajoAsignacion::with('grupoTrabajo')
            ->where('owner_id', $ownerId)
            ->where('estado', 'activa')
            ->orderBy('fecha_fin', 'asc')
            ->get();
    }
}
