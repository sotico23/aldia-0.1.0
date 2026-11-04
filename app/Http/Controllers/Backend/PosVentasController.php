<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\Empleado;
use App\Models\Tesoreria;
use App\Models\Venta;
use App\Services\Inventory\SalesInventoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PosVentasController extends Controller
{
    /**
     * Agrupaciones de métodos de pago expuestas en los filtros.
     *
     * El POS histórico sólo distingue "tarjeta" y "visa_transbank", por lo que
     * ambos se presentan juntos como "Débito / Crédito".
     *
     * @var array<string, list<string>>
     */
    private const GRUPOS_METODO_PAGO = [
        'efectivo' => ['efectivo'],
        'tarjeta' => ['tarjeta', 'visa_transbank'],
        'transferencia' => ['transferencia'],
        'vale' => ['vale'],
        'binance' => ['binance'],
        'contactar' => ['contactar'],
        'otro' => ['otro'],
    ];

    /**
     * @var array<string, string>
     */
    private const ETIQUETAS_METODO_PAGO = [
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Débito / Crédito',
        'transferencia' => 'Transferencia',
        'vale' => 'Vale',
        'binance' => 'Binance',
        'contactar' => 'Contactar',
        'otro' => 'Otro',
    ];

    /**
     * @var list<string>
     */
    private const RANGOS_VALIDOS = ['hoy', 'ayer', 'semana', 'mes', 'personalizado'];

    public function __construct(
        protected SalesInventoryService $salesInventoryService
    ) {}

    /**
     * Historial de ventas y métricas de caja del cajero autenticado.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $ownerId = $user->getOwnerId();
        $puedeVerTodos = $this->puedeVerTodosLosCajeros();
        $puedeAnular = $this->tienePermiso('ventas.pos.delete');

        $filtros = $this->resolverFiltros($request, $puedeVerTodos, $user->id);

        $base = Venta::query()
            ->where('owner_id', $ownerId)
            ->pos()
            ->vigentes();

        if ($filtros['cajero_id'] !== null) {
            $base->delCajero($filtros['cajero_id']);
        }

        $metricas = $this->calcularMetricas($base, $filtros);

        $ventas = (clone $base)
            ->with([
                'cliente:id,nombre,rut',
                'empleado:id,nombre,apellido',
                'almacen:id,nombre',
            ])
            ->when(
                $filtros['tipo_documento'] !== 'todos',
                fn (Builder $query) => $query->tipoDocumento($filtros['tipo_documento'])
            )
            ->when(
                $filtros['metodo_pago'] !== 'todos',
                fn (Builder $query) => $query->metodosPago(self::GRUPOS_METODO_PAGO[$filtros['metodo_pago']])
            )
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Venta $venta) => $this->transformarVenta($venta, $puedeAnular));

        return Inertia::render('Backend/Pos/MisVentas', [
            'ventas' => $ventas,
            'metricas' => $metricas,
            'filtros' => $filtros,
            'cajeros' => $puedeVerTodos ? $this->cajerosDisponibles($ownerId, $user->id, $user->name) : [],
            'puede_ver_todos' => $puedeVerTodos,
            'puede_anular' => $puedeAnular,
            'tipos_documento' => [
                ['value' => 'todos', 'label' => 'Todos'],
                ['value' => 'boleta', 'label' => 'Boleta'],
                ['value' => 'factura', 'label' => 'Factura'],
            ],
            'metodos_pago' => collect(self::ETIQUETAS_METODO_PAGO)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->prepend(['value' => 'todos', 'label' => 'Todos'])
                ->values(),
        ]);
    }

    /**
     * Detalle completo de una venta para el modal de ticket.
     */
    public function show(Venta $venta): JsonResponse
    {
        $this->autorizarAccesoVenta($venta);

        abort_unless($venta->es_pos, 404);

        $venta->load([
            'cliente:id,nombre,rut,direccion,comuna,ciudad',
            'empleado:id,nombre,apellido',
            'almacen:id,nombre',
            'cupon:id,codigo,tipo',
            'detalleVentas.producto:id,nombre,codigo',
        ]);

        return response()->json([
            'venta' => [
                'id' => $venta->id,
                'numero' => $venta->numero,
                'folio' => $venta->folio,
                'documento' => $venta->folio ?: $venta->numero,
                'tipo_documento' => $venta->tipo_documento,
                'metodo_pago' => $venta->metodo_pago,
                'metodo_pago_label' => $this->etiquetaMetodoPago($venta->metodo_pago),
                'estado' => $venta->estado,
                'fecha' => $venta->created_at?->format('Y-m-d'),
                'hora' => $venta->created_at?->format('H:i'),
                'fecha_hora' => $venta->created_at?->format('d/m/Y H:i'),
                'cliente' => $venta->cliente ? [
                    'nombre' => $venta->cliente->nombre,
                    'rut' => $venta->cliente->rut,
                    'direccion' => $venta->cliente->direccion,
                    'comuna' => $venta->cliente->comuna,
                    'ciudad' => $venta->cliente->ciudad,
                ] : null,
                'empleado' => $venta->empleado
                    ? trim("{$venta->empleado->nombre} {$venta->empleado->apellido}")
                    : null,
                'almacen' => $venta->almacen?->nombre,
                'subtotal' => (float) $venta->subtotal,
                'iva' => (float) $venta->iva,
                'descuento' => (float) $venta->descuento,
                'monto_descuento_cupon' => (float) $venta->monto_descuento_cupon,
                'delivery_cost' => (float) $venta->delivery_cost,
                'total' => (float) $venta->total,
                'cupon' => $venta->cupon ? [
                    'codigo' => $venta->cupon->codigo,
                    'tipo' => $venta->cupon->tipo,
                ] : null,
                'items' => $venta->detalleVentas->map(fn ($detalle) => [
                    'id' => $detalle->id,
                    'producto' => $detalle->producto?->nombre ?? 'Producto eliminado',
                    'codigo' => $detalle->producto?->codigo,
                    'cantidad' => (float) $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                    'descuento_unitario' => (float) $detalle->descuento_unitario,
                    'subtotal' => (float) $detalle->subtotal,
                    'cantidad_retornada' => (int) ($detalle->cantidad_retornada ?? 0),
                ])->values(),
                'puede_anular' => $this->puedeAnularVenta($venta),
            ],
        ]);
    }

    /**
     * Genera el ticket PDF con formato de orden de despacho.
     */
    public function ticketPdf(Venta $venta): HttpResponse
    {
        $this->autorizarAccesoVenta($venta);

        abort_unless($venta->es_pos, 404);

        $venta->load([
            'cliente:id,nombre,rut,direccion,comuna,ciudad,telefono',
            'empleado:id,nombre,apellido',
            'almacen:id,nombre',
            'deliveryRate:id,commune,calculation_type,cost',
            'entrega.conductor:id,nombre,telefono',
            'detalleVentas.producto:id,nombre,codigo',
            'owner:id,business_name,name',
        ]);

        $documento = $venta->folio ?: $venta->numero;

        $pdf = Pdf::loadView('pdf.pos-ticket', compact('venta', 'documento'));

        return $pdf->stream('ticket-'.$documento.'.pdf');
    }

    /**
     * Anula una venta POS, restaura inventario y revierte los registros
     * financieros asociados.
     */
    public function anular(Request $request, Venta $venta): RedirectResponse
    {
        abort_unless($this->tienePermiso('ventas.pos.delete'), 403);
        abort_unless($venta->es_pos, 404);

        $this->autorizarAccesoVenta($venta);

        if ($venta->estado === 'anulada') {
            return back()->with('error', 'La venta ya se encuentra anulada.');
        }

        if ($venta->estado !== 'pagada') {
            return back()->with('error', 'Sólo se pueden anular ventas pagadas.');
        }

        $validated = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($venta, $validated) {
                $motivo = trim((string) ($validated['motivo'] ?? ''));
                $nota = "Anulada el ".now()->format('d/m/Y H:i').' por '.Auth::user()->name;

                if ($motivo !== '') {
                    $nota .= ": {$motivo}";
                }

                $venta->update([
                    'estado' => 'anulada',
                    'notas' => trim(($venta->notas ? $venta->notas."\n" : '').$nota),
                ]);

                // Las ventas POS históricas no siempre guardaron el pivote de
                // almacenes; lo aseguramos para que la reposición de stock
                // opere sobre la bodega correcta.
                if ($venta->almacenes()->doesntExist() && $venta->almacen_id) {
                    $venta->almacenes()->sync([$venta->almacen_id]);
                }

                $this->salesInventoryService->restoreSaleInventory($venta);

                Tesoreria::query()
                    ->where('owner_id', $venta->owner_id)
                    ->where('referencia', $venta->numero)
                    ->update(['estado' => 'anulado']);

                $venta->entrega()
                    ->where('estado', 'pendiente')
                    ->update(['estado' => 'cancelada']);

                $this->registrarAsientoAnulacion($venta);
            });

            Log::info('Venta POS anulada', [
                'venta_id' => $venta->id,
                'numero' => $venta->numero,
                'user_id' => Auth::id(),
                'motivo' => $validated['motivo'] ?? null,
            ]);

            return back()->with('success', 'Venta anulada correctamente.');
        } catch (\Throwable $e) {
            Log::error('Error al anular venta POS', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'No fue posible anular la venta: '.$e->getMessage());
        }
    }

    /**
     * @return array{rango: string, fecha_desde: string, fecha_hasta: string, tipo_documento: string, metodo_pago: string, cajero_id: int|null}
     */
    private function resolverFiltros(Request $request, bool $puedeVerTodos, int $userId): array
    {
        $rango = (string) $request->query('rango', 'hoy');

        if (! in_array($rango, self::RANGOS_VALIDOS, true)) {
            $rango = 'hoy';
        }

        $hoy = now();
        $desde = $hoy->copy()->startOfDay();
        $hasta = $hoy->copy()->endOfDay();

        [$desde, $hasta] = match ($rango) {
            'ayer' => [
                $hoy->copy()->subDay()->startOfDay(),
                $hoy->copy()->subDay()->endOfDay(),
            ],
            'semana' => [
                $hoy->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $hoy->copy()->endOfDay(),
            ],
            'mes' => [
                $hoy->copy()->startOfMonth()->startOfDay(),
                $hoy->copy()->endOfDay(),
            ],
            'personalizado' => $this->resolverRangoPersonalizado($request, $hoy),
            default => [$desde, $hasta],
        };

        $tipoDocumento = (string) $request->query('tipo_documento', 'todos');
        if (! in_array($tipoDocumento, ['todos', 'boleta', 'factura'], true)) {
            $tipoDocumento = 'todos';
        }

        $metodoPago = (string) $request->query('metodo_pago', 'todos');
        if ($metodoPago !== 'todos' && ! array_key_exists($metodoPago, self::GRUPOS_METODO_PAGO)) {
            $metodoPago = 'todos';
        }

        $cajeroId = $this->resolverCajeroId($request, $puedeVerTodos, $userId);

        return [
            'rango' => $rango,
            'fecha_desde' => $desde->toDateString(),
            'fecha_hasta' => $hasta->toDateString(),
            'tipo_documento' => $tipoDocumento,
            'metodo_pago' => $metodoPago,
            'cajero_id' => $cajeroId,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolverRangoPersonalizado(Request $request, Carbon $hoy): array
    {
        $desde = $this->parseFecha($request->query('fecha_desde'))
            ?? $hoy->copy()->startOfMonth();
        $hasta = $this->parseFecha($request->query('fecha_hasta'))
            ?? $hoy->copy();

        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->endOfDay();

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }

        return [$desde, $hasta];
    }

    private function parseFecha(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolverCajeroId(Request $request, bool $puedeVerTodos, int $userId): ?int
    {
        if (! $puedeVerTodos) {
            return $userId;
        }

        $cajeroParam = $request->query('cajero_id');

        if ($cajeroParam === 'all') {
            return null;
        }

        if ($cajeroParam === null || $cajeroParam === '') {
            return $userId;
        }

        $cajeroId = (int) $cajeroParam;

        return $cajeroId > 0 ? $cajeroId : $userId;
    }

    /**
     * @return array{
     *     dia: array{total: float, cantidad: int},
     *     mes: array{total: float, cantidad: int},
     *     ticket_promedio: float,
     *     metodo_predominante: array{clave: string, etiqueta: string, total: float, cantidad: int, porcentaje: float}|null,
     *     desglose_metodos: array<int, array{clave: string, etiqueta: string, total: float, cantidad: int, porcentaje: float}>
     * }
     */
    private function calcularMetricas(Builder $base, array $filtros): array
    {
        $hoy = now();

        $dia = (clone $base)
            ->whereDate('fecha', $hoy->toDateString())
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total')
            ->first();

        $mes = (clone $base)
            ->entreFechas(
                $hoy->copy()->startOfMonth(),
                $hoy->copy()->endOfMonth()
            )
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total')
            ->first();

        $desglose = $this->calcularDesgloseMetodos($base, $filtros);
        $predominante = $desglose[0] ?? null;

        $cantidadDia = (int) ($dia->cantidad ?? 0);
        $totalDia = (float) ($dia->total ?? 0);

        return [
            'dia' => [
                'total' => $totalDia,
                'cantidad' => $cantidadDia,
            ],
            'mes' => [
                'total' => (float) ($mes->total ?? 0),
                'cantidad' => (int) ($mes->cantidad ?? 0),
            ],
            'ticket_promedio' => $cantidadDia > 0 ? round($totalDia / $cantidadDia, 2) : 0.0,
            'metodo_predominante' => $predominante,
            'desglose_metodos' => $desglose,
        ];
    }

    /**
     * @return array<int, array{clave: string, etiqueta: string, total: float, cantidad: int, porcentaje: float}>
     */
    private function calcularDesgloseMetodos(Builder $base, array $filtros): array
    {
        $rows = (clone $base)
            ->entreFechas($filtros['fecha_desde'], $filtros['fecha_hasta'])
            ->select(
                'metodo_pago',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('COALESCE(SUM(total), 0) as total')
            )
            ->groupBy('metodo_pago')
            ->get();

        $agrupado = [];
        $totalPeriodo = 0.0;

        foreach ($rows as $row) {
            $clave = $this->normalizarClaveMetodo((string) $row->metodo_pago);

            if (! isset($agrupado[$clave])) {
                $agrupado[$clave] = [
                    'clave' => $clave,
                    'etiqueta' => $this->etiquetaMetodoPago($clave),
                    'total' => 0.0,
                    'cantidad' => 0,
                    'porcentaje' => 0.0,
                ];
            }

            $total = (float) $row->total;
            $agrupado[$clave]['total'] += $total;
            $agrupado[$clave]['cantidad'] += (int) $row->cantidad;
            $totalPeriodo += $total;
        }

        return collect($agrupado)
            ->map(function (array $item) use ($totalPeriodo): array {
                $item['porcentaje'] = $totalPeriodo > 0
                    ? round(($item['total'] / $totalPeriodo) * 100, 1)
                    : 0.0;

                return $item;
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function transformarVenta(Venta $venta, bool $puedeAnular): array
    {
        return [
            'id' => $venta->id,
            'numero' => $venta->numero,
            'folio' => $venta->folio,
            'documento' => $venta->folio ?: $venta->numero,
            'fecha' => $venta->created_at?->format('Y-m-d'),
            'hora' => $venta->created_at?->format('H:i'),
            'fecha_hora' => $venta->created_at?->format('d/m/Y H:i'),
            'cliente' => $venta->cliente?->nombre ?? 'Cliente genérico',
            'cliente_rut' => $venta->cliente?->rut,
            'tipo_documento' => $venta->tipo_documento,
            'metodo_pago' => $venta->metodo_pago,
            'metodo_pago_label' => $this->etiquetaMetodoPago($venta->metodo_pago),
            'total' => (float) $venta->total,
            'estado' => $venta->estado,
            'empleado' => $venta->empleado
                ? trim("{$venta->empleado->nombre} {$venta->empleado->apellido}")
                : null,
            'almacen' => $venta->almacen?->nombre,
            'puede_anular' => $puedeAnular && $venta->estado === 'pagada',
        ];
    }

    private function normalizarClaveMetodo(string $metodoPago): string
    {
        return $metodoPago === 'visa_transbank' ? 'tarjeta' : $metodoPago;
    }

    private function etiquetaMetodoPago(string $metodoPago): string
    {
        $clave = $this->normalizarClaveMetodo($metodoPago);

        return self::ETIQUETAS_METODO_PAGO[$clave]
            ?? ucfirst(str_replace('_', ' ', $clave));
    }

    private function puedeVerTodosLosCajeros(): bool
    {
        return Auth::user()->hasAnyRole(['Master', 'Super Admin', 'Administrador', 'Owner'])
            || $this->tienePermiso('ventas.pos-reportes.viewAny');
    }

    private function puedeAnularVenta(Venta $venta): bool
    {
        return $venta->es_pos
            && $venta->estado === 'pagada'
            && $this->tienePermiso('ventas.pos.delete');
    }

    private function tienePermiso(string $permission): bool
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['Master', 'Super Admin'])) {
            return true;
        }

        return $user->getAllPermissions()->contains('name', $permission);
    }

    private function autorizarAccesoVenta(Venta $venta): void
    {
        if (! $this->puedeVerTodosLosCajeros() && (int) $venta->user_id !== (int) Auth::id()) {
            abort(403, 'No tienes permiso para consultar esta venta.');
        }
    }

    /**
     * @return array<int, array{id: int, nombre: string}>
     */
    private function cajerosDisponibles(int $ownerId, int $userId, string $userName): array
    {
        $cajeros = Empleado::query()
            ->where('owner_id', $ownerId)
            ->whereNotNull('user_id')
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get(['user_id', 'nombre', 'apellido'])
            ->map(fn (Empleado $empleado) => [
                'id' => (int) $empleado->user_id,
                'nombre' => trim("{$empleado->nombre} {$empleado->apellido}"),
            ])
            ->unique('id')
            ->values();

        if (! $cajeros->contains('id', $userId)) {
            $cajeros->prepend([
                'id' => $userId,
                'nombre' => $userName,
            ]);
        }

        return $cajeros->all();
    }

    /**
     * Genera el asiento contable de reversa cuando la venta fue contabilizada.
     */
    private function registrarAsientoAnulacion(Venta $venta): void
    {
        $asientoOriginal = Asiento::query()
            ->where('owner_id', $venta->owner_id)
            ->where('descripcion', 'LIKE', "%Venta #{$venta->numero}%")
            ->exists();

        if (! $asientoOriginal) {
            return;
        }

        $numero = 'AS-ANL-'.str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT);

        $asiento = Asiento::firstOrCreate(
            ['numero' => $numero],
            [
                'owner_id' => $venta->owner_id,
                'fecha' => now()->toDateString(),
                'descripcion' => "Anulación Venta POS #{$venta->numero}",
                'tipo' => 'anulacion',
                'total_debe' => $venta->total,
                'total_haber' => $venta->total,
                'estado' => true,
            ]
        );

        if (! $asiento->wasRecentlyCreated) {
            return;
        }

        $asiento->detalles()->create([
            'cuenta' => 'Ventas',
            'cuenta_codigo' => '4.1.01',
            'descripcion' => 'Reversa de venta POS',
            'debe' => $venta->subtotal,
            'haber' => 0,
        ]);

        if ($venta->iva > 0) {
            $asiento->detalles()->create([
                'cuenta' => 'IVA Débito Fiscal',
                'cuenta_codigo' => '2.1.03',
                'descripcion' => 'Reversa de impuesto sobre ventas POS',
                'debe' => $venta->iva,
                'haber' => 0,
            ]);
        }

        $asiento->detalles()->create([
            'cuenta' => 'Caja/Banco',
            'cuenta_codigo' => '1.1.01',
            'descripcion' => 'Reversa de ingreso por venta POS',
            'debe' => 0,
            'haber' => $venta->total,
        ]);
    }
}
