<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Scopes\OwnerScope;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard de ganancias del repartidor.
 *
 * GET /api/v1/driver/earnings?dias=7
 *
 * Devuelve el resumen del período, la serie diaria para el gráfico semanal y
 * las últimas entregas completadas. Las ganancias se congelan en el pedido al
 * marcarlo como entregado (`ganancia_repartidor`).
 */
class DriverEarningsController extends Controller
{
    private const DIAS_POR_DEFECTO = 7;

    private const DIAS_MAX = 90;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dias' => ['nullable', 'integer', 'min:1', 'max:'.self::DIAS_MAX],
        ]);

        $dias = (int) ($validated['dias'] ?? self::DIAS_POR_DEFECTO);
        $repartidor = $this->repartidor($request);

        $to = now();
        $from = now()->startOfDay()->subDays($dias - 1);

        $pedidos = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $repartidor->owner_id)
            ->where('repartidor_id', $request->user()->id)
            ->where('estado', 'entregado')
            ->whereNotNull('hora_entregado')
            ->whereBetween('hora_entregado', [$from, $to])
            ->orderByDesc('hora_entregado')
            ->get();

        $totalGanado = 0.0;
        $totalEnvios = 0.0;

        $detalle = $pedidos->map(function (Pedido $pedido) use ($repartidor, &$totalGanado, &$totalEnvios) {
            $costoEnvio = $pedido->costoEnvioEfectivo();
            $ganancia = $pedido->gananciaPara($repartidor);

            $totalGanado += $ganancia;
            $totalEnvios += $costoEnvio;

            return [
                'id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'nombre_cliente' => $pedido->nombre_cliente ?? 'Cliente',
                'direccion_cliente' => $pedido->direccion_cliente ?? '',
                'total' => (float) ($pedido->total ?? 0),
                'costo_envio' => $costoEnvio,
                'ganancia' => $ganancia,
                'metodo_pago' => $pedido->metodo_pago ?? 'Efectivo',
                'fecha' => $pedido->hora_entregado?->toISOString(),
            ];
        });

        $hoy = $pedidos
            ->filter(fn (Pedido $pedido) => $pedido->hora_entregado?->isToday())
            ->sum(fn (Pedido $pedido) => $pedido->gananciaPara($repartidor));

        $porDia = [];
        $cursor = $from->copy()->startOfDay();
        $ultimoDia = $to->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($ultimoDia)) {
            $delDia = $pedidos->filter(
                fn (Pedido $pedido) => $pedido->hora_entregado?->isSameDay($cursor)
            );

            $porDia[] = [
                'fecha' => $cursor->toDateString(),
                'dia' => $this->diaLabel($cursor),
                'total' => round($delDia->sum(
                    fn (Pedido $pedido) => $pedido->gananciaPara($repartidor)
                ), 0),
                'entregas' => $delDia->count(),
            ];

            $cursor = $cursor->addDay();
        }

        $totalEntregas = $pedidos->count();

        return response()->json([
            'success' => true,
            'dias' => $dias,
            'periodo' => [
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
            ],
            'resumen' => [
                'total_ganado' => round($totalGanado, 0),
                'total_envios' => round($totalEnvios, 0),
                'promedio_por_entrega' => $totalEntregas > 0
                    ? round($totalGanado / $totalEntregas, 0)
                    : 0,
                'hoy' => round((float) $hoy, 0),
                'comision_pct' => (float) $repartidor->comision_pct,
                'total_entregas' => $totalEntregas,
            ],
            'por_dia' => $porDia,
            'ultimas_entregas' => $detalle->take(10)->values()->all(),
        ]);
    }

    private function repartidor(Request $request): Repartidor
    {
        return Repartidor::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function diaLabel(CarbonInterface $fecha): string
    {
        return match ($fecha->dayOfWeekIso) {
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
            default => 'Dom',
        };
    }
}
