<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\Entrega;
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

        $monto = Venta::whereIn('id', $ventaIds)->sum('total');
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
        $totalKg = 0;
        $totalL = 0;

        if ($ventaIds->isEmpty()) {
            return [0, 0];
        }

        $detalles = DetalleVenta::whereIn('venta_id', $ventaIds)
            ->with('producto')
            ->get();

        foreach ($detalles as $detalle) {
            $producto = $detalle->producto;
            if (! $producto) {
                continue;
            }

            $cantidad = (float) $detalle->cantidad;
            $valorMedida = (float) ($producto->cantidad_medida ?: 1);
            $tipoMedida = strtolower($producto->tipo_medida ?? '');
            $unidadMedida = strtolower($producto->unidad_medida ?? '');

            $esKg = $tipoMedida === 'kg' || $tipoMedida === 'kilo' || $tipoMedida === 'kilos'
                || $unidadMedida === 'kg';
            $esL = $tipoMedida === 'l' || $tipoMedida === 'litro' || $tipoMedida === 'litros'
                || $unidadMedida === 'l';

            if ($esKg) {
                $totalKg += $cantidad * ($valorMedida ?: 1);
            } elseif ($esL) {
                $totalL += $cantidad * ($valorMedida ?: 1);
            }
        }

        return [$totalKg, $totalL];
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
