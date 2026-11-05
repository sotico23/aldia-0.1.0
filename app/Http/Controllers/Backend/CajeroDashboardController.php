<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CajeroDashboardController extends Controller
{
    /**
     * Panel individual del cajero autenticado: KPIs, ranking de productos,
     * ventas recientes y consejos calculados a partir de su propia operacion.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $hoy = Carbon::today();
        $inicioMes = $hoy->copy()->startOfMonth();
        $finMes = $hoy->copy()->endOfMonth();

        $baseCajero = Venta::query()
            ->delCajero($user->id)
            ->vigentes();

        $ventasHoy = $this->resumenPeriodo(
            (clone $baseCajero)->whereDate('fecha', $hoy->toDateString())
        );

        $ventasMes = $this->resumenPeriodo(
            (clone $baseCajero)->entreFechas($inicioMes, $finMes)
        );

        $gananciaHoy = DetalleVenta::query()
            ->whereIn(
                'venta_id',
                (clone $baseCajero)->whereDate('fecha', $hoy->toDateString())->select('ventas.id')
            )
            ->sum('comision_cajero');

        $gananciaMes = DetalleVenta::query()
            ->whereIn(
                'venta_id',
                (clone $baseCajero)->entreFechas($inicioMes, $finMes)->select('ventas.id')
            )
            ->sum('comision_cajero');

        $unidadesHoy = (float) (clone $baseCajero)
            ->whereDate('fecha', $hoy->toDateString())
            ->join('detalle_ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->sum('detalle_ventas.cantidad');

        $topProductos = $this->topProductos(
            $user->id,
            $inicioMes,
            $finMes
        );

        $ultimasVentas = $this->ultimasVentas($user->id, $hoy);

        $stockCritico = $this->contarStockCritico();
        $moneda = $user->getCountryConfig()?->currency_code ?? 'CLP';

        $kpis = [
            'ventas_hoy' => $ventasHoy,
            'ventas_mes' => $ventasMes,
            'ganancia_hoy' => (float) $gananciaHoy,
            'ganancia_mes' => (float) $gananciaMes,
            'ticket_promedio' => $ventasHoy['documentos'] > 0
                ? round($ventasHoy['total'] / $ventasHoy['documentos'], 2)
                : 0.0,
            'productos_hoy' => [
                'unidades' => round($unidadesHoy, 2),
                'top' => $topProductos[0]['nombre'] ?? null,
            ],
        ];

        $empleado = $user->empleado()->with('almacen:id,nombre')->first();

        return Inertia::render('Backend/Pos/CajeroDashboard', [
            'cajero' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'nombre_empleado' => $empleado?->nombre_completo,
                'cargo' => $empleado?->cargo,
                'almacen' => $empleado?->almacen?->nombre,
            ],
            'contexto' => [
                'fecha' => $hoy->format('d/m/Y'),
                'fecha_larga' => ucfirst(
                    $hoy->copy()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')
                ),
                'hora' => now()->format('H:i'),
                'turno' => $this->resolverTurno((int) now()->format('G')),
            ],
            'kpis' => $kpis,
            'top_productos' => $topProductos,
            'ultimas_ventas' => $ultimasVentas,
            'stock_critico' => $stockCritico,
            'tips' => $this->generarTips($kpis, $topProductos, $stockCritico, $moneda),
            'moneda' => $moneda,
        ]);
    }

    /**
     * @return array{total: float, documentos: int}
     */
    private function resumenPeriodo(Builder $query): array
    {
        $resumen = $query
            ->selectRaw('COUNT(*) as documentos, COALESCE(SUM(total), 0) as total')
            ->first();

        return [
            'total' => (float) ($resumen->total ?? 0),
            'documentos' => (int) ($resumen->documentos ?? 0),
        ];
    }

    /**
     * Ranking de productos vendidos por el cajero durante el mes en curso.
     *
     * @return array<int, array{id: int, nombre: string, codigo: string|null, cantidad: float, total: float}>
     */
    private function topProductos(int $userId, Carbon $inicioMes, Carbon $finMes): array
    {
        return Venta::query()
            ->where('ventas.user_id', $userId)
            ->vigentes()
            ->entreFechas($inicioMes, $finMes)
            ->join('detalle_ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'productos.id', '=', 'detalle_ventas.producto_id')
            ->groupBy('productos.id', 'productos.nombre', 'productos.codigo')
            ->orderByDesc('cantidad')
            ->limit(5)
            ->selectRaw('productos.id, productos.nombre, productos.codigo, SUM(detalle_ventas.cantidad) as cantidad, SUM(detalle_ventas.subtotal) as total')
            ->get()
            ->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'nombre' => (string) $fila->nombre,
                'codigo' => $fila->codigo,
                'cantidad' => round((float) $fila->cantidad, 2),
                'total' => round((float) $fila->total, 2),
            ])
            ->all();
    }

    /**
     * Ultimas ventas del cajero. Prioriza las del dia y, si no tiene
     * movimientos hoy, muestra las mas recientes de su historial.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ultimasVentas(int $userId, Carbon $hoy): array
    {
        $base = Venta::query()
            ->delCajero($userId)
            ->vigentes()
            ->with('cliente:id,nombre,rut');

        $ventas = (clone $base)
            ->whereDate('fecha', $hoy->toDateString())
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        if ($ventas->isEmpty()) {
            $ventas = (clone $base)
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->limit(8)
                ->get();
        }

        return $ventas
            ->map(fn (Venta $venta) => [
                'id' => $venta->id,
                'documento' => $venta->folio ?: $venta->numero,
                'fecha' => $venta->fecha?->format('d/m/Y'),
                'hora' => $venta->created_at?->format('H:i'),
                'cliente' => $venta->cliente?->nombre ?? 'Cliente generico',
                'tipo_documento' => $venta->tipo_documento,
                'tipo_documento_label' => $this->etiquetaTipoDocumento((string) $venta->tipo_documento),
                'metodo_pago' => $venta->metodo_pago,
                'metodo_pago_label' => $this->etiquetaMetodoPago((string) $venta->metodo_pago),
                'total' => (float) $venta->total,
                'estado' => $venta->estado,
            ])
            ->all();
    }

    private function contarStockCritico(): int
    {
        return Producto::query()
            ->where('activo', true)
            ->where('stock_minimo', '>', 0)
            ->whereRaw(
                'productos.stock_minimo >= (select COALESCE(sum(cantidad), 0) from inventarios where inventarios.producto_id = productos.id)'
            )
            ->count();
    }

    /**
     * @return array{nombre: string, rango: string}
     */
    private function resolverTurno(int $hora): array
    {
        return match (true) {
            $hora < 12 => ['nombre' => 'Turno manana', 'rango' => '08:00 - 12:00'],
            $hora < 19 => ['nombre' => 'Turno tarde', 'rango' => '12:00 - 19:00'],
            default => ['nombre' => 'Turno noche', 'rango' => '19:00 - 23:00'],
        };
    }

    /**
     * Consejos comerciales generados a partir de la operacion real del cajero.
     *
     * @param  array<string, mixed>  $kpis
     * @param  array<int, array<string, mixed>>  $topProductos
     * @return array<int, array{titulo: string, descripcion: string, icono: string, tono: string}>
     */
    private function generarTips(array $kpis, array $topProductos, int $stockCritico, string $moneda): array
    {
        $documentosHoy = (int) $kpis['ventas_hoy']['documentos'];
        $totalHoy = (float) $kpis['ventas_hoy']['total'];
        $ticketPromedio = (float) $kpis['ticket_promedio'];
        $top = $topProductos[0] ?? null;
        $tips = [];

        if ($documentosHoy === 0) {
            $tips[] = [
                'titulo' => 'Activa tu primera venta del dia',
                'descripcion' => 'Aun no registras movimientos hoy. Revisa los productos de mayor rotacion y ofrece una recomendacion al cliente desde el primer cobro.',
                'icono' => 'sparkles',
                'tono' => 'info',
            ];
        } else {
            $tips[] = [
                'titulo' => 'Buen ritmo de caja',
                'descripcion' => 'Llevas '.$documentosHoy.' '.($documentosHoy === 1 ? 'venta' : 'ventas').' hoy por '.MoneyHelper::formatWithSymbol($totalHoy, $moneda).'. Manten la amabilidad y ofrece un complemento en cada cobro.',
                'icono' => 'trending-up',
                'tono' => 'success',
            ];
        }

        if ($top) {
            $tips[] = [
                'titulo' => 'Tu producto estrella: '.$top['nombre'],
                'descripcion' => 'Ya vendiste '.$this->formatearCantidad((float) $top['cantidad']).' unidades este mes. Mantenlo a la vista y sugierelo al cerrar cada venta.',
                'icono' => 'package',
                'tono' => 'neutral',
            ];
        }

        if ($stockCritico > 0) {
            $tips[] = [
                'titulo' => 'Revisa el stock antes de vender',
                'descripcion' => 'Hay '.$stockCritico.' '.($stockCritico === 1 ? 'producto' : 'productos').' en o por debajo del stock minimo. Confirma disponibilidad para evitar quiebres en caja.',
                'icono' => 'alert-triangle',
                'tono' => 'warning',
            ];
        }

        $tips[] = [
            'titulo' => 'Sube tu ticket promedio',
            'descripcion' => $documentosHoy > 0
                ? 'Tu ticket promedio de hoy es '.MoneyHelper::formatWithSymbol($ticketPromedio, $moneda).'. Ofrece una presentacion mayor o un producto complementario.'
                : 'Cuando registres tu primera venta, ofrece un complemento para elevar el ticket promedio.',
            'icono' => 'target',
            'tono' => 'info',
        ];

        $tips[] = [
            'titulo' => $documentosHoy >= 5 ? 'Meta del dia en marcha' : 'Meta del dia',
            'descripcion' => $documentosHoy >= 5
                ? 'Superaste las 5 ventas. Fija la proxima meta en '.((int) (ceil(($documentosHoy + 1) / 5) * 5)).' documentos y manten el ritmo.'
                : 'Te faltan '.(5 - $documentosHoy).' '.((5 - $documentosHoy) === 1 ? 'venta' : 'ventas').' para alcanzar los 5 documentos de hoy.',
            'icono' => 'flag',
            'tono' => 'neutral',
        ];

        return array_slice($tips, 0, 5);
    }

    private function formatearCantidad(float $cantidad): string
    {
        return floor($cantidad) === $cantidad
            ? number_format($cantidad, 0, ',', '.')
            : number_format($cantidad, 1, ',', '.');
    }

    private function etiquetaTipoDocumento(string $tipo): string
    {
        return match ($tipo) {
            'factura' => 'Factura',
            'nota_credito' => 'Nota de credito',
            'cotizacion' => 'Cotizacion',
            default => 'Boleta',
        };
    }

    private function etiquetaMetodoPago(string $metodo): string
    {
        return match ($metodo) {
            'tarjeta', 'visa_transbank' => 'Debito / Credito',
            'transferencia' => 'Transferencia',
            'vale' => 'Vale',
            'binance' => 'Binance',
            'contactar' => 'Contactar',
            'otro' => 'Otro',
            default => 'Efectivo',
        };
    }
}
