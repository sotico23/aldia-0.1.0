<?php

namespace App\Http\Controllers\Backend;

use App\Enums\Currency;
use App\Exports\VentasExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\FiltraPorCliente;
use App\Imports\VentasImport;
use App\Models\Almacen;
use App\Models\Asiento;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\CuponUsoDetalle;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Tesoreria;
use App\Models\Venta;
use App\Models\WebSetting;
use App\Services\Inventory\SalesInventoryService;
use App\Services\Sii\CafManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VentaController extends Controller implements HasMiddleware
{
    use AuthorizesRequests, FiltraPorCliente;

    protected SalesInventoryService $salesInventoryService;

    public function __construct(SalesInventoryService $salesInventoryService)
    {
        $this->salesInventoryService = $salesInventoryService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:ventas.ventas.create', only: ['create', 'store']),
            new Middleware('permission:ventas.ventas.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:ventas.ventas.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Venta::with(['cliente', 'almacen', 'detalleVentas.producto', 'almacenes'])->where('owner_id', $ownerId)->orderBy('created_at', 'desc');

        if ($this->usuarioEsCliente()) {
            $query->where(fn ($q) => $q->where(fn ($q) => $q->where('cliente_id', $this->getClienteAuth()->id)));
        }

        $ventas = $query->paginate(15)->through(function ($venta) {
            // almacen already eager-loaded via with([almacen]) on line 47

            return [
                'id' => $venta->id,
                'numero_factura' => $venta->numero_factura,
                'cliente_id' => $venta->cliente_id,
                'fecha' => $venta->fecha?->format('Y-m-d'),
                'subtotal' => $venta->subtotal,
                'iva' => $venta->iva,
                'total' => $venta->total,
                'estado' => $venta->estado,
                'notas' => $venta->notas,
                'incluye_iva' => $venta->incluye_iva,
                'tipo_descuento' => $venta->tipo_descuento,
                'valor_descuento' => $venta->valor_descuento,
                'monto_descuento' => $venta->monto_descuento,
                'almacen_id' => $venta->almacen_id,
                'almacen_nombre' => $venta->almacen?->nombre,
                'almacen_ids' => $venta->almacenes->pluck('id'),
                'almacenes_data' => $venta->almacenes->map(function ($almacen) {
                    return [
                        'id' => $almacen->id,
                        'nombre' => $almacen->nombre,
                        'pivot' => [
                            'cantidad_descontada' => $almacen->pivot?->cantidad_descontada ?? 0,
                        ],
                    ];
                }),
                'cliente' => $venta->cliente,
                'currency' => $venta->currency ?? 'CLP',
                'detalle_ventas' => $venta->detalleVentas->map(fn ($d) => [
                    'id' => $d->id,
                    'producto_id' => $d->producto_id,
                    'cantidad' => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'subtotal' => $d->subtotal,
                    'producto' => $d->producto,
                ]),
            ];
        });
        $clientes = Cliente::where('owner_id', $ownerId)->where('activo', true)->get();
        $productos = Producto::with('inventarios')->where('owner_id', $ownerId)->where('activo', true)->get();
        $almacenes = Almacen::where('owner_id', $ownerId)->where('activo', true)->get();

        $resumenVentas = [
            'total_ventas' => Venta::where('owner_id', $ownerId)->count(),
            'pagadas' => Venta::where('owner_id', $ownerId)->where('estado', 'pagada')->count(),
            'pendientes' => Venta::where('owner_id', $ownerId)->where('estado', 'pendiente')->count(),
            'ingresos' => (float) Venta::where('owner_id', $ownerId)->where('estado', 'pagada')->sum('total'),
        ];

        $months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $ventasBase = Venta::where('owner_id', $ownerId);

        // Ventas por Mes (últimos 6 meses)
        $monthlyData = collect();
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $monthlyData->put($key, 0);
        }
        $ventasBase->clone()
            ->where('fecha', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as periodo, SUM(total) as total")
            ->groupBy('periodo')
            ->get()
            ->each(function ($row) use ($monthlyData) {
                if ($monthlyData->has($row->periodo)) {
                    $monthlyData->put($row->periodo, (float) $row->total);
                }
            });
        $ventasPorMes = $monthlyData->map(function ($total, $key) use ($months) {
            $m = (int) substr($key, 5, 2);

            return ['mes' => $months[$m - 1] ?? $key, 'total' => $total];
        })->values();

        // Ventas por Semana (últimas 6 semanas)
        $weeklyData = collect();
        for ($i = 5; $i >= 0; $i--) {
            $end = now()->subWeeks($i)->endOfWeek();
            $start = (clone $end)->subDays(6)->startOfDay();
            $weeklyData->put($start->format('Y-m-d'), [
                'label' => $start->format('j/n').'-'.$end->format('j/n'),
                'start' => $start,
                'end' => $end,
                'total' => 0,
            ]);
        }
        $ventasBase->clone()
            ->where('fecha', '>=', $weeklyData->first()['start'])
            ->where('fecha', '<=', $weeklyData->last()['end'])
            ->selectRaw('DATE(fecha) as dia, SUM(total) as total')
            ->groupBy('dia')
            ->get()
            ->each(function ($row) use ($weeklyData) {
                $dt = Carbon::parse($row->dia);
                $weeklyData->each(function ($week, $key) use ($dt, $row, $weeklyData) {
                    if ($dt >= $week['start'] && $dt <= $week['end']) {
                        $current = $weeklyData->get($key);
                        $current['total'] += (float) $row->total;
                        $weeklyData->put($key, $current);
                    }
                });
            });
        $ventasPorSemana = $weeklyData->map(fn ($w) => ['mes' => $w['label'], 'total' => $w['total']])->values();

        // Ventas por Día (últimos 7 días)
        $dailyData = collect();
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $dailyData->put($day->format('Y-m-d'), [
                'label' => $day->format('j/n'),
                'start' => $day->copy()->startOfDay(),
                'end' => $day->copy()->endOfDay(),
                'total' => 0,
            ]);
        }
        $ventasBase->clone()
            ->where('fecha', '>=', $dailyData->first()['start'])
            ->where('fecha', '<=', $dailyData->last()['end'])
            ->selectRaw('DATE(fecha) as dia, SUM(total) as total')
            ->groupBy('dia')
            ->get()
            ->each(function ($row) use ($dailyData) {
                $key = Carbon::parse($row->dia)->format('Y-m-d');
                if ($dailyData->has($key)) {
                    $current = $dailyData->get($key);
                    $current['total'] += (float) $row->total;
                    $dailyData->put($key, $current);
                }
            });
        $ventasPorDia = $dailyData->map(fn ($d) => ['mes' => $d['label'], 'total' => $d['total']])->values();

        return Inertia::render('Backend/Ventas/Index', [
            'ventas' => $ventas,
            'clientes' => $clientes,
            'productos' => $productos,
            'almacenes' => $almacenes,
            'resumenVentas' => $resumenVentas,
            'ventasPorMes' => $ventasPorMes,
            'ventasPorSemana' => $ventasPorSemana,
            'ventasPorDia' => $ventasPorDia,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Log::info('VentaController::store called', [
            'user_id' => auth()->id(),
            'tipo_documento' => $request->input('tipo_documento'),
            'productos_count' => count($request->input('productos', [])),
            'estado' => $request->input('estado'),
        ]);
        $ownerId = auth()->user()->getOwnerId();
        $validated = $request->validate([
            'numero_factura' => 'required|string|max:50|unique:ventas,numero',
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('owner_id', $ownerId)],
            'cliente_tipo' => 'nullable|in:existente,generico',
            'cliente_nombre' => 'nullable|string|max:255',
            'cliente_rut' => 'nullable|string|max:20',
            'cliente_telefono' => 'nullable|string|max:30',
            'cliente_direccion' => 'nullable|string|max:500',
            'fecha' => 'required|date',
            'estado' => 'required|in:pendiente,pagada,cancelada',
            'notas' => 'nullable|string',
            'incluye_iva' => 'nullable|boolean',
            'tipo_descuento' => 'nullable|in:monto,porcentaje',
            'valor_descuento' => 'nullable|numeric|min:0',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => ['required', Rule::exists('productos', 'id')->where('owner_id', $ownerId)],
            'productos.*.cantidad' => 'required|numeric|min:0.001',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.cantidad_retornada' => 'nullable|integer|min:0',
            'almacen_ids' => 'required|array|min:1',
            'almacen_ids.*' => ['required', Rule::exists('almacenes', 'id')->where('owner_id', $ownerId)],
            'tipo_documento' => 'nullable|in:boleta,factura,nota_credito,cotizacion',
            'cupon_codigo' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'allow_negative_stock' => 'nullable|boolean',
        ]);

        $tipoDoc = $validated['tipo_documento'] ?? 'boleta';
        $folioData = null;

        // Obtener folio SII si es factura o boleta
        if (in_array($tipoDoc, ['factura', 'boleta'])) {
            $tipoDte = $tipoDoc === 'factura' ? 33 : 39;
            try {
                $cafManager = new CafManager;
                $ownerId = auth()->user()->getOwnerId();
                $folioData = $cafManager->obtenerSiguienteFolio($tipoDte, $ownerId);
            } catch (\Exception $e) {
                Log::warning('No se pudo obtener folio SII: '.$e->getMessage());
            }
        }

        // Si es cliente genérico, crear cliente temporal
        $clienteId = $validated['cliente_id'];
        if (($validated['cliente_tipo'] ?? 'existente') === 'generico' && ! empty($validated['cliente_nombre'])) {
            $cliente = Cliente::create([
                'nombre' => $validated['cliente_nombre'],
                'rut' => $validated['cliente_rut'] ?? null,
                'telefono' => $validated['cliente_telefono'] ?? null,
                'direccion' => $validated['cliente_direccion'] ?? null,
                'activo' => true,
            ]);
            $clienteId = $cliente->id;
        }

        // Validar que tenga cliente
        if (! $clienteId) {
            return back()->withErrors(['cliente_id' => 'Debe seleccionar un cliente o ingresar datos del cliente genérico']);
        }

        $subtotal = 0;
        $envasesExtra = [];
        foreach ($validated['productos'] as $item) {
            $subtotal += round($item['cantidad'] * $item['precio_unitario']);
            $producto = Producto::find($item['producto_id']);
            if ($producto && $producto->envase_retornable && $producto->envase_producto_id && $producto->envase_producto_id != $producto->id) {
                $cantidadRetornada = $item['cantidad_retornada'] ?? 0;
                $envasesPendientes = $item['cantidad'] - $cantidadRetornada;
                if ($envasesPendientes > 0) {
                    $envaseProducto = Producto::find($producto->envase_producto_id);
                    if ($envaseProducto) {
                        $costoExtra = round($envasesPendientes * $envaseProducto->precio_venta);
                        $subtotal += $costoExtra;
                        $envasesExtra[] = [
                            'producto_id' => $envaseProducto->id,
                            'cantidad' => $envasesPendientes,
                            'precio_unitario' => round($envaseProducto->precio_venta),
                            'subtotal' => (int) $costoExtra,
                            'cantidad_retornada' => null,
                        ];
                    }
                }
            }
        }

        $tipoDescuento = $validated['tipo_descuento'] ?? 'monto';
        $valorDescuento = $validated['valor_descuento'] ?? 0;
        $montoDescuento = 0;

        if ($tipoDescuento === 'porcentaje') {
            $montoDescuento = round($subtotal * ($valorDescuento / 100));
        } else {
            $montoDescuento = round($valorDescuento);
        }

        // Validar cupón (fuera de transacción para early return con error)
        $montoCupon = 0.0;
        $cuponId = null;
        $cupon = null;
        if ($cuponCodigo = $validated['cupon_codigo'] ?? null) {
            $cupon = Cupon::where('codigo', $cuponCodigo)
                ->active()
                ->with('productos:id,nombre,precio_venta')
                ->first();

            if (! $cupon || ! $cupon->validar((float) $subtotal, auth()->id())) {
                return back()->withErrors(['cupon_codigo' => 'El cupón ingresado no es válido o ha expirado.']);
            }

            $items = array_map(fn ($item) => [
                'producto_id' => $item['producto_id'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio_unitario'],
            ], $validated['productos']);

            $montoCupon = $cupon->calcularDescuentoProductos($items);
            $cuponId = $cupon->id;
        }

        $montoDescuentoTotal = (int) round($montoDescuento + $montoCupon);
        $baseImponible = max(0, $subtotal - $montoDescuentoTotal);
        $incluyeIva = $validated['incluye_iva'] ?? true;
        $iva = $incluyeIva ? round($baseImponible * config('taxes.iva_rate')) : 0;
        $total = $baseImponible + $iva;

        $almacenIds = $validated['almacen_ids'];

        $ventaData = [
            'numero' => $validated['numero_factura'],
            'cliente_id' => $clienteId,
            'fecha' => $validated['fecha'],
            'subtotal' => (int) $subtotal,
            'iva' => (int) $iva,
            'total' => (int) $total,
            'monto_descuento' => (int) $montoDescuentoTotal,
            'valor_descuento' => $valorDescuento,
            'tipo_descuento' => $tipoDescuento,
            'incluye_iva' => $incluyeIva,
            'estado' => $validated['estado'],
            'notas' => $validated['notas'] ?? null,
            'almacen_id' => $almacenIds[0],
            'tipo_documento' => $tipoDoc,
            'cupon_id' => $cuponId,
            'monto_descuento_cupon' => $montoCupon ?: null,
            'currency' => $validated['currency'] ?? $this->resolveCurrency(),
        ];

        // Agregar folio si está disponible
        if ($folioData) {
            $ventaData['folio'] = $folioData['folio'];
            $ventaData['tipo_dte'] = $tipoDte;
        }

        $venta = DB::transaction(function () use ($ventaData, $almacenIds, $validated, $cuponId, $cupon, $montoCupon, $total) {
            // Canjear cupón dentro de la transacción (atómico)
            if ($cupon && $cuponId) {
                if (! $cupon->canjear(auth()->id())) {
                    throw new \RuntimeException('El cupón no pudo ser canjeado. Verifique que no haya alcanzado el límite de usos.');
                }
            }

            $venta = Venta::create($ventaData);
            $venta->almacenes()->sync(array_map(fn ($id) => ['almacen_id' => $id], $almacenIds));

            if ($cuponId && $cupon) {
                $uso = CuponUso::create([
                    'cupon_id' => $cuponId,
                    'venta_id' => $venta->id,
                    'user_id' => auth()->id(),
                    'monto_total' => (float) $total,
                    'monto_descuento' => $montoCupon,
                ]);

                // Registrar detalle por producto si es vale_producto
                if ($cupon->esValeProducto()) {
                    $productosConfig = $cupon->productos->keyBy('id');
                    foreach ($validated['productos'] as $item) {
                        $productoId = (int) $item['producto_id'];
                        if (! $productosConfig->has($productoId)) {
                            continue;
                        }
                        $pivot = $productosConfig->get($productoId)->pivot;
                        $cantidad = (float) $item['cantidad'];
                        $precioUnitario = (float) $item['precio_unitario'];
                        $descuentoItem = 0.0;

                        if ($pivot->descuento_tipo === 'precio_fijo' && $pivot->descuento_valor > 0) {
                            $descuentoItem = max(0, ($precioUnitario - (float) $pivot->descuento_valor) * $cantidad);
                        } else {
                            $descuentoItem = round($precioUnitario * $cantidad * ((float) $cupon->valor / 100), 2);
                        }

                        CuponUsoDetalle::create([
                            'cupon_uso_id' => $uso->id,
                            'producto_id' => $productoId,
                            'cantidad' => (int) $cantidad,
                            'precio_unitario' => $precioUnitario,
                            'descuento_tipo' => $pivot->descuento_tipo,
                            'descuento_valor' => $pivot->descuento_valor,
                            'monto_descuento' => $descuentoItem,
                        ]);
                    }
                }
            }

            foreach ($validated['productos'] as $item) {
                $subtotalItem = round($item['cantidad'] * $item['precio_unitario']);

                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => round($item['precio_unitario']),
                    'subtotal' => (int) $subtotalItem,
                    'cantidad_retornada' => $item['cantidad_retornada'] ?? null,
                ]);
            }

            $this->procesarPago($venta);

            return $venta;
        });

        return redirect()->route('ventas.index');
    }

    private function verificarStockDisponible(array $productos, array $almacenIds): void
    {
        foreach ($productos as $item) {
            $productoId = (int) $item['producto_id'];
            $cantidadRequerida = (float) $item['cantidad'];

            // Sumar stock disponible en todos los almacenes
            $stockTotal = $this->sumarStockEnAlmacenes($productoId, $almacenIds);

            if ($stockTotal < $cantidadRequerida) {
                throw new \RuntimeException(
                    "Stock insuficiente para el producto #{$productoId}.
                     Necesita: {$cantidadRequerida}, Disponible: {$stockTotal}"
                );
            }
        }
    }

    private function sumarStockEnAlmacenes(int $productoId, array $almacenIds): float
    {
        $total = 0.0;
        foreach ($almacenIds as $almacenId) {
            $inventario = Inventario::where('producto_id', $productoId)
                ->where('almacen_id', $almacenId)
                ->first();

            if ($inventario) {
                $total += (float) $inventario->cantidad;
            }
        }

        return $total;
    }

    public function update(Request $request, Venta $venta): RedirectResponse
    {
        $this->authorize('update', $venta);

        $ownerId = auth()->user()->getOwnerId();
        $validated = $request->validate([
            'numero_factura' => 'nullable|string|max:50|unique:ventas,numero,'.$venta->id,
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('owner_id', $ownerId)],
            'fecha' => 'nullable|date',
            'estado' => 'nullable|in:pendiente,pagada,cancelada',
            'notas' => 'nullable|string',
            'incluye_iva' => 'nullable|boolean',
            'tipo_descuento' => 'nullable|in:monto,porcentaje',
            'valor_descuento' => 'nullable|numeric|min:0',
            'productos' => 'nullable|array|min:1',
            'productos.*.producto_id' => ['nullable', Rule::exists('productos', 'id')->where('owner_id', $ownerId)],
            'productos.*.cantidad' => 'nullable|numeric|min:0.001',
            'productos.*.precio_unitario' => 'nullable|numeric|min:0',
            'productos.*.cantidad_retornada' => 'nullable|integer|min:0',
            'almacen_ids' => 'nullable|array|min:1',
            'almacen_ids.*' => ['required', Rule::exists('almacenes', 'id')->where('owner_id', $ownerId)],
            'currency' => 'nullable|string|max:10',
        ]);

        $estadoAnterior = $venta->estado;

        $subtotal = 0;
        $envasesExtra = [];
        $items = $validated['productos'] ?? $venta->detalleVentas;
        foreach ($items as $item) {
            $cantidad = isset($item['cantidad']) ? $item['cantidad'] : $item->cantidad;
            $precio = isset($item['precio_unitario']) ? $item['precio_unitario'] : $item->precio_unitario;
            $subtotal += round($cantidad * $precio);

            $productoId = isset($item['producto_id']) ? $item['producto_id'] : $item->producto_id;
            $producto = Producto::find($productoId);
            if ($producto && $producto->envase_retornable && $producto->envase_producto_id) {
                $cantidadRetornada = isset($item['cantidad_retornada']) ? $item['cantidad_retornada'] : ($item->cantidad_retornada ?? 0);
                $envasesPendientes = $cantidad - $cantidadRetornada;
                if ($envasesPendientes > 0) {
                    $envaseProducto = Producto::find($producto->envase_producto_id);
                    if ($envaseProducto) {
                        $costoExtra = round($envasesPendientes * $envaseProducto->precio_venta);
                        $subtotal += $costoExtra;
                        $envasesExtra[] = [
                            'producto_id' => $envaseProducto->id,
                            'cantidad' => $envasesPendientes,
                            'precio_unitario' => round($envaseProducto->precio_venta),
                            'subtotal' => (int) $costoExtra,
                            'cantidad_retornada' => null,
                        ];
                    }
                }
            }
        }

        $tipoDescuento = $validated['tipo_descuento'] ?? $venta->tipo_descuento;
        $valorDescuento = $validated['valor_descuento'] ?? $venta->valor_descuento;
        $montoDescuento = 0;

        if ($tipoDescuento === 'porcentaje') {
            $montoDescuento = round($subtotal * ($valorDescuento / 100));
        } else {
            $montoDescuento = round($valorDescuento);
        }

        $baseImponible = max(0, $subtotal - $montoDescuento);
        $incluyeIva = $validated['incluye_iva'] ?? $venta->incluye_iva;
        $iva = $incluyeIva ? round($baseImponible * config('taxes.iva_rate')) : 0;
        $total = $baseImponible + $iva;

        $almacenIdsUpdate = $validated['almacen_ids'] ?? null;

        $venta->update([
            'numero' => $validated['numero_factura'] ?? $venta->numero,
            'cliente_id' => $validated['cliente_id'] ?? $venta->cliente_id,
            'fecha' => $validated['fecha'] ?? $venta->fecha,
            'subtotal' => (int) $subtotal,
            'iva' => (int) $iva,
            'total' => (int) $total,
            'monto_descuento' => (int) $montoDescuento,
            'valor_descuento' => $valorDescuento,
            'tipo_descuento' => $tipoDescuento,
            'incluye_iva' => (bool) $incluyeIva,
            'estado' => $validated['estado'] ?? $venta->estado,
            'notas' => $validated['notas'] ?? $venta->notas,
            'almacen_id' => $almacenIdsUpdate ? $almacenIdsUpdate[0] : $venta->almacen_id,
            'currency' => $validated['currency'] ?? $venta->currency ?? $this->resolveCurrency(),
        ]);

        if ($almacenIdsUpdate) {
            $venta->almacenes()->sync(array_map(fn ($id) => ['almacen_id' => $id], $almacenIdsUpdate));
        }

        // Re-sync productos: delete old and create new
        if (isset($validated['productos'])) {
            $oldDetalles = $estadoAnterior === 'pagada'
                ? $venta->detalleVentas->keyBy('producto_id')
                : collect();
            $venta->detalleVentas()->delete();

            foreach ($validated['productos'] as $item) {
                $subtotalItem = round($item['cantidad'] * $item['precio_unitario']);

                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => round($item['precio_unitario']),
                    'subtotal' => (int) $subtotalItem,
                    'cantidad_retornada' => $item['cantidad_retornada'] ?? null,
                ]);
            }

            if ($estadoAnterior === 'pagada' && $venta->estado === 'pagada') {
                $this->ajustarInventarioUpdate($venta, $validated['productos'], $oldDetalles);
            }
        }

        if ($estadoAnterior !== 'pagada' && $venta->estado === 'pagada') {
            $this->procesarPago($venta);
        }

        return redirect()->route('ventas.index');
    }

    public function destroy(Venta $venta): RedirectResponse
    {
        $this->authorize('delete', $venta);

        if ($this->usuarioEsCliente() && $venta->cliente_id !== $this->getClienteAuth()->id) {
            abort(403, 'No tienes permiso para eliminar esta venta.');
        }

        if ($venta->estado === 'pagada') {
            $this->restaurarInventario($venta);
        }

        $venta->delete();

        return redirect()->route('ventas.index');
    }

    public function updateStatus(Request $request, Venta $venta): RedirectResponse
    {
        $validated = $request->validate([
            'estado' => 'required|in:pendiente,pagada,cancelada',
        ]);

        $estadoAnterior = $venta->estado;

        $venta->update([
            'estado' => $validated['estado'],
        ]);

        if ($estadoAnterior !== 'pagada' && $venta->estado === 'pagada') {
            $this->procesarPago($venta);
        }

        if ($estadoAnterior === 'pagada' && in_array($venta->estado, ['cancelada', 'pendiente'])) {
            $this->restaurarInventario($venta);
        }

        return redirect()->back();
    }

    public function downloadPdf(Venta $venta)
    {
        $venta->load(['cliente', 'detalleVentas.producto']);
        $logo = $this->resolveLogoPath();

        $pdf = Pdf::loadView('pdf.venta', compact('venta', 'logo'));

        return $pdf->download('venta_'.$venta->numero.'.pdf');
    }

    public function downloadPdfInformal(Venta $venta)
    {
        $venta->load(['cliente', 'detalleVentas.producto']);
        $logo = $this->resolveLogoPath();

        $pdf = Pdf::loadView('pdf.venta-informal', compact('venta', 'logo'));

        return $pdf->download('venta_'.$venta->numero.'_simple.pdf');
    }

    private function resolveLogoPath(): string
    {
        $user = auth()->user();

        if ($user && $user->business_logo_path) {
            $path = storage_path('app/public/'.$user->business_logo_path);
            if (file_exists($path)) {
                return $path;
            }
        }

        $settings = WebSetting::getSettings();

        return $settings->app_logo ? public_path($settings->app_logo) : public_path('favicon.svg');
    }

    protected function getExportClass(array $filters): object
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Venta::with(['cliente', 'detalleVentas.producto'])->where('owner_id', $ownerId);

        if (! empty($filters['fecha_desde'])) {
            $query->where('fecha', '>=', Carbon::parse($filters['fecha_desde'])->startOfDay());
        }
        if (! empty($filters['fecha_hasta'])) {
            $query->where('fecha', '<=', Carbon::parse($filters['fecha_hasta'])->endOfDay());
        }
        if (! empty($filters['cliente_id']) && $filters['cliente_id'] !== 'all') {
            $query->where('cliente_id', $filters['cliente_id']);
        }
        if (! empty($filters['estado']) && $filters['estado'] !== 'all') {
            $query->where('estado', $filters['estado']);
        }
        if (! empty($filters['search'])) {
            $search = SearchHelper::escapeLike($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('nombre', 'like', "%{$search}%");
                    });
            });
        }
        if ($this->usuarioEsCliente()) {
            $query->where('cliente_id', $this->getClienteAuth()->id);
        }

        return new VentasExport($query->orderBy('fecha', 'desc')->get());
    }

    public function exportExcel(Request $request)
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Venta::with(['cliente', 'detalleVentas.producto'])->where('owner_id', $ownerId);

        if ($request->filled('busqueda')) {
            $search = SearchHelper::escapeLike($request->input('busqueda'));
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('cliente', fn ($pq) => $pq->where('nombre', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->input('cliente_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('fechaDesde')) {
            $query->where('fecha', '>=', Carbon::parse($request->input('fechaDesde'))->startOfDay());
        }
        if ($request->filled('fechaHasta')) {
            $query->where('fecha', '<=', Carbon::parse($request->input('fechaHasta'))->endOfDay());
        }

        if ($this->usuarioEsCliente()) {
            $query->where('cliente_id', $this->getClienteAuth()->id);
        }

        return Excel::download(new VentasExport($query->orderBy('fecha', 'desc')->get()), 'ventas_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Venta::with(['cliente', 'detalleVentas.producto'])->where('owner_id', $ownerId);

        if ($request->filled('busqueda')) {
            $search = SearchHelper::escapeLike($request->input('busqueda'));
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('cliente', fn ($pq) => $pq->where('nombre', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->input('cliente_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('fechaDesde')) {
            $query->where('fecha', '>=', Carbon::parse($request->input('fechaDesde'))->startOfDay());
        }
        if ($request->filled('fechaHasta')) {
            $query->where('fecha', '<=', Carbon::parse($request->input('fechaHasta'))->endOfDay());
        }

        if ($this->usuarioEsCliente()) {
            $query->where('cliente_id', $this->getClienteAuth()->id);
        }

        $ventas = $query->orderBy('fecha', 'desc')->get();
        $filename = 'ventas_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($ventas) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['Numero', 'Fecha', 'Cliente', 'Estado', 'Subtotal', 'IVA', 'Total', 'Notas'], ';');

            foreach ($ventas as $venta) {
                fputcsv($file, [
                    $venta->numero,
                    $venta->fecha?->format('Y-m-d'),
                    $venta->cliente?->nombre ?? 'N/A',
                    $venta->estado,
                    $venta->subtotal,
                    $venta->iva,
                    $venta->total,
                    $venta->notas,
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt,xlsx,xls']);
        $import = new VentasImport(auth()->user()->getOwnerId());
        Excel::import($import, $request->file('archivo'));

        $message = "Importación completada: {$import->getImportedCount()} ventas importadas";
        if ($import->getSkippedCount() > 0) {
            $message .= ", {$import->getSkippedCount()} omitidas";
        }
        if ($errors = $import->getErrors()) {
            $message .= '. Advertencias: '.implode('; ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= ' y '.(count($errors) - 3).' más';
            }
        }

        return redirect()->back()->with('success', $message);
    }

    public function importExcel(Request $request): RedirectResponse
    {
        return $this->importCsv($request);
    }

    protected function getImportClass(): object
    {
        return new VentasImport(auth()->user()->getOwnerId());
    }

    private function procesarPago(Venta $venta): void
    {
        DB::transaction(function () use ($venta) {
            // 1. Registro en Tesorería (Flujo de Caja)
            $existeTesoreria = Tesoreria::where(fn ($q) => $q->where('referencia', $venta->numero))->exists();
            if (! $existeTesoreria) {
                Tesoreria::create([
                    'owner_id' => $venta->owner_id,
                    'tipo' => 'ingreso',
                    'monto' => $venta->total,
                    'descripcion' => "Ingreso por Venta #{$venta->numero}",
                    'fecha' => now(),
                    'referencia' => $venta->numero,
                    'estado' => 'completado',
                ]);
            }

            // 2. Registro Contable (Asiento Diario)
            $existeAsiento = Asiento::where(fn ($q) => $q->where('descripcion', 'LIKE', "%Venta #{$venta->numero}%"))->exists();
            if (! $existeAsiento) {
                $asiento = Asiento::create([
                    'owner_id' => $venta->owner_id,
                    'fecha' => now(),
                    'numero' => 'AS-VNT-'.str_pad($venta->id, 6, '0', STR_PAD_LEFT),
                    'descripcion' => "Registro de contable Venta #{$venta->numero}",
                    'tipo' => 'venta',
                    'total_debe' => $venta->total,
                    'total_haber' => $venta->total,
                    'estado' => true,
                ]);

                $asiento->detalles()->create([
                    'cuenta' => 'Caja/Banco',
                    'cuenta_codigo' => '1.1.01',
                    'descripcion' => 'Ingreso por venta',
                    'debe' => $venta->total,
                    'haber' => 0,
                ]);

                $asiento->detalles()->create([
                    'cuenta' => 'Ventas',
                    'cuenta_codigo' => '4.1.01',
                    'descripcion' => 'Venta de productos/servicios',
                    'debe' => 0,
                    'haber' => $venta->subtotal,
                ]);

                if ($venta->iva > 0) {
                    $asiento->detalles()->create([
                        'cuenta' => 'IVA Débito Fiscal',
                        'cuenta_codigo' => '2.1.03',
                        'descripcion' => 'Impuesto sobre ventas',
                        'debe' => 0,
                        'haber' => $venta->iva,
                    ]);
                }
            }

            // 3. Procesar Inventario y Envases usando el servicio compartido
            $items = $venta->detalleVentas->map(fn ($item) => [
                'producto_id' => $item->producto_id,
                'cantidad' => $item->cantidad,
                'cantidad_retornada' => $item->cantidad_retornada ?? 0,
                'almacen_id' => $venta->almacen_id,
            ])->toArray();

            $almacenIds = $venta->almacenes->pluck('id')->toArray();
            if ($venta->almacen_id && ! in_array($venta->almacen_id, $almacenIds)) {
                $almacenIds[] = $venta->almacen_id;
            }

            $this->salesInventoryService->processSaleInventory(
                venta: $venta,
                items: $items,
                ownerId: $venta->owner_id,
                userId: $venta->user_id ?? auth()->id(),
                almacenIds: $almacenIds
            );
        });
    }

    private function restaurarInventario(Venta $venta): void
    {
        $this->salesInventoryService->restoreSaleInventory($venta);
    }

    private function ajustarInventarioUpdate(Venta $venta, array $newItems, Collection $oldDetalles): void
    {
        $this->salesInventoryService->adjustInventoryOnUpdate($venta, $newItems, $oldDetalles);
    }

    private function resolveCurrency(): string
    {
        $user = auth()->user();
        if ($user && $user->country) {
            return Currency::fromCountry($user->country)->value;
        }

        return Currency::default();
    }
}
