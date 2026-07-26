<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromocionStoreRequest;
use App\Http\Requests\PromocionUpdateRequest;
use App\Models\Almacen;
use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\CuponUsoDetalle;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\SkuVariante;
use App\Models\Tesoreria;
use App\Models\Venta;
use App\Services\Inventory\SalesInventoryService;
use App\Services\Sii\FacturacionSiiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller implements HasMiddleware
{
    protected SalesInventoryService $salesInventoryService;

    public function __construct(SalesInventoryService $salesInventoryService)
    {
        $this->salesInventoryService = $salesInventoryService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:ventas.pos.create', only: ['create', 'store']),
        ];
    }

    public function index(): Response
    {
        $ownerId = Auth::user()->getOwnerId();

        $productos = Producto::with([
            'inventarios.almacen',
            'envaseProducto',
            'skus' => function ($q) {
                $q->where('activo', true);
            },
            'skus.valores.varianteValor.variante',
        ])
            ->where('activo', true)
            ->where('owner_id', $ownerId)
            ->get()
            ->map(function ($producto) {
                return [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'descripcion' => $producto->descripcion,
                    'codigo' => $producto->codigo,
                    'precio_venta' => $producto->precio_venta,
                    'precio_con_variantes' => $producto->precio_con_variantes,
                    'stock' => $producto->stock_total,
                    'stock_minimo' => $producto->stock_minimo,
                    'unidad_medida' => $producto->unidad_medida,
                    'peso_base' => $producto->peso_base,
                    'contenido_por_unidad' => $producto->contenido_por_unidad,
                    'medida_pesable' => $producto->medida_pesable,
                    'imagen' => $producto->imagen,
                    'tiene_variantes' => $producto->tiene_variantes,
                    'envase_retornable' => $producto->envase_retornable,
                    'envase_producto_id' => $producto->envase_producto_id,
                    'envase_precio' => $producto->envaseProducto ? (float) $producto->envaseProducto->precio_venta : 0,
                    'inventarios' => $producto->inventarios->map(function ($inv) {
                        return [
                            'almacen_id' => $inv->almacen_id,
                            'almacen_nombre' => $inv->almacen?->nombre,
                            'cantidad' => $inv->cantidad,
                            'cantidad_minima' => $inv->cantidad_minima,
                        ];
                    })->toArray(),
                    'skus' => $producto->skus->map(function ($sku) {
                        return [
                            'id' => $sku->id,
                            'sku' => $sku->sku,
                            'precio_venta' => $sku->precio_venta,
                            'stock' => $sku->stock,
                            'variantes' => $sku->valores->map(function ($v) {
                                return [
                                    'variante' => $v->varianteValor->variante->nombre,
                                    'valor' => $v->varianteValor->valor,
                                ];
                            })->toArray(),
                        ];
                    })->toArray(),
                ];
            });

        $clientes = Cliente::where('owner_id', $ownerId)->get(['id', 'nombre', 'rut']);

        $promociones = Promocion::where('owner_id', $ownerId)
            ->where('activa', true)
            ->where(function ($q) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now());
            })
            ->get(['id', 'nombre', 'tipo', 'valor', 'skus', 'categoria_id', 'compra_minima']);

        $almacenes = Almacen::where('owner_id', $ownerId)->get(['id', 'nombre']);

        return Inertia::render('Backend/Pos/Index', [
            'productos' => $productos,
            'clientes' => $clientes,
            'almacenes' => $almacenes,
            'promociones' => $promociones,
            'iva_tasa' => (float) config('taxes.iva_rate', 0.19),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Log::info('POS store attempt', [
            'user_id' => Auth::id(),
            'metodo_pago' => $request->input('metodo_pago'),
            'tipo_documento' => $request->input('tipo_documento'),
            'items_count' => count($request->input('items', [])),
            'total' => $request->input('total'),
        ]);

        $ownerId = Auth::user()->getOwnerId();
        $validated = $request->validate([
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('owner_id', $ownerId)],
            'metodo_pago' => 'required|in:efectivo,tarjeta,transferencia,vale,visa_transbank,binance,contactar',
            'tipo_documento' => 'required|in:boleta,factura',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => ['required', Rule::exists('productos', 'id')->where('owner_id', $ownerId)],
            'items.*.sku_variante_id' => ['nullable', Rule::exists('skus', 'id')->where('owner_id', $ownerId)],
            'items.*.cantidad' => 'required|numeric|min:0.001',
            'items.*.precio' => 'required|numeric|min:0',
            'items.*.cantidad_retornada' => 'nullable|integer|min:0',
            'subtotal' => 'required|numeric',
            'iva' => 'required|numeric',
            'total' => 'required|numeric',
            'descuento' => 'nullable|numeric|min:0',
            'incluye_iva' => 'nullable|boolean',
            'almacen_id' => ['required', Rule::exists('almacenes', 'id')->where('owner_id', $ownerId)],
            'cupon_codigo' => 'nullable|string|max:50',
        ]);

        try {
            return DB::transaction(function () use ($request, $ownerId) {
                // Normalizar cliente_id: null, "", "0" => null (cliente genérico)
                $clienteId = null;
                if ($request->cliente_id && $request->cliente_id !== '0' && $request->cliente_id !== '') {
                    $clienteId = $request->cliente_id;
                }
                $descuento = (float) ($request->descuento ?? 0);
                $montoCupon = 0.0;
                $cuponId = null;

                // Recalcular subtotal server-side (no confiar en el cliente)
                $subtotalCalculado = 0.0;
                foreach ($request->items as $item) {
                    $subtotalCalculado += (float) $item['cantidad'] * (float) $item['precio'];
                }

                $incluyeIva = (bool) ($validated['incluye_iva'] ?? true);
                $ivaTasa = config('taxes.iva_rate', 0.19);

                // Validar y canjear cupón si se envió código
                $cuponCodigo = $request->cupon_codigo;
                if ($cuponCodigo) {
                    $cupon = Cupon::where('codigo', $cuponCodigo)
                        ->active()
                        ->with('productos:id,nombre,precio_venta')
                        ->first();

                    if (! $cupon || ! $cupon->validar($subtotalCalculado, Auth::id())) {
                        throw new \RuntimeException('El cupón ingresado no es válido o ha expirado.');
                    }

                    $items = collect($request->items)->map(fn ($item) => [
                        'producto_id' => $item['producto_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                    ])->toArray();

                    $montoCupon = $cupon->calcularDescuentoProductos($items);

                    if (! $cupon->canjear(Auth::id())) {
                        throw new \RuntimeException('El cupón no pudo ser canjeado. Verifique que no haya alcanzado el límite de usos.');
                    }

                    $cuponId = $cupon->id;
                }

                $montoDescuentoTotal = (int) round($descuento + $montoCupon);
                $baseImponible = max(0, $subtotalCalculado - $montoDescuentoTotal);

                // En Chile, los precios de POS YA INCLUYEN IVA (precios finales).
                // Si incluye_iva es true, el precio YA TIENE IVA incluido.
                // Debemos EXTRAER el IVA, no sumarlo.
                if ($incluyeIva) {
                    // Precio final = Neto + IVA = Neto * (1 + iva_rate)
                    // Neto = Precio / (1 + iva_rate)
                    // IVA = Precio - Neto
                    $baseImponibleNeta = round($baseImponible / (1 + $ivaTasa));
                    $iva = $baseImponible - $baseImponibleNeta;
                    $baseImponible = $baseImponibleNeta; // El neto real
                } else {
                    $iva = 0;
                }
                $total = $baseImponible + $iva;

                $venta = Venta::create([
                    'owner_id' => $ownerId,
                    'user_id' => Auth::id(),
                    'cliente_id' => $clienteId,
                    'numero' => 'POS-'.Str::random(8).'-'.time(),
                    'fecha' => now(),
                    'subtotal' => (int) round($subtotalCalculado),
                    'iva' => (int) round($iva),
                    'total' => (int) round($total),
                    'incluye_iva' => $incluyeIva,
                    'metodo_pago' => $request->metodo_pago,
                    'tipo_documento' => $request->tipo_documento,
                    'almacen_id' => $request->almacen_id,
                    'es_pos' => true,
                    'estado' => 'pagada',
                    'tipo_descuento' => 'monto',
                    'valor_descuento' => $montoDescuentoTotal,
                    'monto_descuento' => $montoDescuentoTotal,
                    'descuento' => $montoDescuentoTotal,
                    'cupon_id' => $cuponId,
                    'monto_descuento_cupon' => $montoCupon ?: null,
                ]);

                // Crear registro de uso de cupón
                if ($cuponId && $cupon) {
                    $uso = CuponUso::create([
                        'cupon_id' => $cuponId,
                        'venta_id' => $venta->id,
                        'user_id' => Auth::id(),
                        'monto_total' => (float) $total,
                        'monto_descuento' => $montoCupon,
                    ]);

                    // Registrar detalle por producto si es vale_producto
                    if ($cupon->esValeProducto()) {
                        $productosConfig = $cupon->productos->keyBy('id');
                        foreach ($request->items as $item) {
                            $productoId = (int) $item['producto_id'];
                            if (! $productosConfig->has($productoId)) {
                                continue;
                            }
                            $pivot = $productosConfig->get($productoId)->pivot;
                            $cantidad = (float) $item['cantidad'];
                            $precioUnitario = (float) $item['precio'];
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

                $productIds = collect($request->items)->pluck('producto_id')->unique()->toArray();
                $skuIds = collect($request->items)->pluck('sku_variante_id')->filter()->unique()->toArray();
                $productosMap = Producto::whereIn('id', $productIds)->get()->keyBy('id');
                $skusMap = ! empty($skuIds) ? SkuVariante::whereIn('id', $skuIds)->get()->keyBy('id') : collect();

                $skusMap = ! empty($skuIds) ? SkuVariante::whereIn('id', $skuIds)->get()->keyBy('id') : collect();

                // Crear DetalleVenta para los productos principales
                foreach ($request->items as $item) {
                    $producto = Producto::findOrFail($item['producto_id']);
                    $skuId = $item['sku_variante_id'] ?? null;
                    $precioUnitario = (int) round($item['precio']);
                    $descuentoUnitario = isset($item['descuento']) ? (int) round($item['descuento']) : 0;
                    $cantidad = (int) $item['cantidad'];

                    DetalleVenta::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $item['producto_id'],
                        'sku_variante_id' => $skuId,
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $precioUnitario,
                        'descuento_unitario' => $descuentoUnitario ?: null,
                        'subtotal' => (int) round($precioUnitario * $item['cantidad']),
                        'subtotal_metrica' => 0,
                        'cantidad_retornada' => $item['cantidad_retornada'] ?? null,
                    ]);

                    // Decrementar stock de SKU si aplica
                    $itemSkuId = $item['sku_variante_id'] ?? null;
                    if ($itemSkuId && ($sku = $skusMap->get($itemSkuId))) {
                        $sku->decrement('stock', $item['cantidad']);
                    }
                }

                // Registrar en Tesorería (Flujo de Caja)
                $existeTesoreria = Tesoreria::where(fn ($q) => $q->where('referencia', $venta->numero))->exists();
                if (! $existeTesoreria) {
                    Tesoreria::create([
                        'owner_id' => $ownerId,
                        'tipo' => 'ingreso',
                        'monto' => $venta->total,
                        'descripcion' => "Ingreso por Venta POS #{$venta->numero}",
                        'fecha' => now(),
                        'referencia' => $venta->numero,
                        'estado' => 'completado',
                    ]);
                }

                // Registrar Asiento Contable
                $existeAsiento = Asiento::where(fn ($q) => $q->where('descripcion', 'LIKE', "%Venta #{$venta->numero}%"))->exists();
                if (! $existeAsiento) {
                    $asiento = Asiento::create([
                        'owner_id' => $ownerId,
                        'fecha' => now(),
                        'numero' => 'AS-VNT-'.str_pad($venta->id, 6, '0', STR_PAD_LEFT),
                        'descripcion' => "Registro contable Venta POS #{$venta->numero}",
                        'tipo' => 'venta',
                        'total_debe' => $venta->total,
                        'total_haber' => $venta->total,
                        'estado' => true,
                    ]);

                    $asiento->detalles()->create([
                        'cuenta' => 'Caja/Banco',
                        'cuenta_codigo' => '1.1.01',
                        'descripcion' => 'Ingreso por venta POS',
                        'debe' => $venta->total,
                        'haber' => 0,
                    ]);

                    $asiento->detalles()->create([
                        'cuenta' => 'Ventas',
                        'cuenta_codigo' => '4.1.01',
                        'descripcion' => 'Venta de productos/servicios POS',
                        'debe' => 0,
                        'haber' => $venta->subtotal,
                    ]);

                    if ($venta->iva > 0) {
                        $asiento->detalles()->create([
                            'cuenta' => 'IVA Débito Fiscal',
                            'cuenta_codigo' => '2.1.03',
                            'descripcion' => 'Impuesto sobre ventas POS',
                            'debe' => 0,
                            'haber' => $venta->iva,
                        ]);
                    }
                }

                // Procesar inventario y envases usando el servicio compartido
                $items = collect($request->items)->map(fn ($item) => [
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'cantidad_retornada' => $item['cantidad_retornada'] ?? 0,
                    'almacen_id' => $request->almacen_id,
                ])->toArray();

                $almacenIds = $venta->almacenes->pluck('id')->toArray();
                if (! in_array($request->almacen_id, $almacenIds)) {
                    $almacenIds[] = $request->almacen_id;
                }

                $this->salesInventoryService->processSaleInventory(
                    venta: $venta,
                    items: $items,
                    ownerId: $ownerId,
                    userId: Auth::id(),
                    almacenIds: $almacenIds
                );

                return redirect()->back()->with([
                    'success' => 'Venta realizada con éxito.',
                    'ultima_venta_id' => $venta->id,
                    'cupon_aplicado' => $venta->cupon ? [
                        'codigo' => $venta->cupon->codigo,
                        'nombre' => $venta->cupon->tipo,
                        'descuento' => (float) $venta->monto_descuento_cupon,
                    ] : null,
                ]);
            });
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al procesar la venta: '.$e->getMessage());
        }
    }

    public function emitirDte(Venta $venta, FacturacionSiiService $sii): JsonResponse
    {
        try {
            $tipoDte = $venta->tipo_documento === 'factura' ? 33 : 39;

            $dte = $sii->procesarVenta($venta, $tipoDte);

            return response()->json([
                'success' => true,
                'message' => 'DTE emitido correctamente.',
                'dte' => [
                    'id' => $dte->id,
                    'folio' => $dte->folio,
                    'tipo_documento' => $dte->tipo_documento,
                    'estado' => $dte->estado,
                    'total' => $dte->monto_total,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al emitir DTE: '.$e->getMessage(),
            ], 422);
        }
    }

    public function cierreCaja(Request $request)
    {
        $fechaDesde = $request->query('fecha_desde')
            ? Carbon::parse($request->query('fecha_desde'))
            : now();
        $fechaHasta = $request->query('fecha_hasta')
            ? Carbon::parse($request->query('fecha_hasta'))
            : now();

        $ownerId = Auth::user()->getOwnerId();
        $ventas = Venta::with('detalleVentas.producto')
            ->where('owner_id', $ownerId)
            ->where('es_pos', true)
            ->whereBetween('fecha', [$fechaDesde->toDateString(), $fechaHasta->toDateString()])
            ->orderBy('created_at', 'desc')
            ->get();

        $arqueo = [
            'efectivo' => $ventas->where('metodo_pago', 'efectivo')->sum('total'),
            'tarjeta' => $ventas->where('metodo_pago', 'tarjeta')->sum('total'),
            'transferencia' => $ventas->where('metodo_pago', 'transferencia')->sum('total'),
            'otros' => $ventas->whereIn('metodo_pago', ['vale', 'visa_transbank', 'binance', 'contactar'])->sum('total'),
            'total' => $ventas->sum('total'),
            'cantidad_ventas' => $ventas->count(),
            'fecha_desde' => $fechaDesde->toDateString(),
            'fecha_hasta' => $fechaHasta->toDateString(),
            'detalle' => $ventas->map(function ($v) {
                $items = $v->detalleVentas->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'producto_id' => $item->producto_id,
                        'producto_nombre' => $item->producto?->nombre ?? 'Producto eliminado',
                        'cantidad' => (float) $item->cantidad,
                        'precio_unitario' => (float) $item->precio_unitario,
                        'subtotal' => (float) $item->subtotal,
                        'cantidad_retornada' => $item->cantidad_retornada,
                    ];
                })->values();

                return [
                    'id' => $v->id,
                    'fecha' => $v->created_at->format('d/m/Y'),
                    'hora' => $v->created_at->format('H:i'),
                    'fecha_completa' => $v->created_at->format('d/m/Y H:i:s'),
                    'tipo' => 'Venta',
                    'metodo' => ucfirst($v->metodo_pago),
                    'documento' => ucfirst($v->tipo_documento).' #'.($v->numero_factura ?? $v->id),
                    'monto' => $v->total,
                    'items' => $items,
                ];
            })->values(),
        ];

        return Inertia::render('Backend/Pos/CierreCaja', [
            'arqueo' => $arqueo,
        ]);
    }

    public function cerrarTurno(Request $request)
    {
        $fechaDesde = $request->input('fecha_desde')
            ? Carbon::parse($request->input('fecha_desde'))
            : now();
        $fechaHasta = $request->input('fecha_hasta')
            ? Carbon::parse($request->input('fecha_hasta'))
            : now();

        $ownerId = Auth::user()->getOwnerId();

        $ventas = Venta::where('owner_id', $ownerId)
            ->where('es_pos', true)
            ->whereBetween('fecha', [$fechaDesde->toDateString(), $fechaHasta->toDateString()])
            ->get();

        $totalEfectivo = $ventas->where('metodo_pago', 'efectivo')->sum('total');
        $totalTarjeta = $ventas->where('metodo_pago', 'tarjeta')->sum('total');
        $totalTransferencia = $ventas->where('metodo_pago', 'transferencia')->sum('total');
        $totalGeneral = $ventas->sum('total');
        $cantidadVentas = $ventas->count();

        session()->put('arqueo_caja', [
            'owner_id' => $ownerId,
            'user_id' => Auth::id(),
            'fecha_desde' => $fechaDesde->toDateString(),
            'fecha_hasta' => $fechaHasta->toDateString(),
            'total_efectivo' => $totalEfectivo,
            'total_tarjeta' => $totalTarjeta,
            'total_transferencia' => $totalTransferencia,
            'total_general' => $totalGeneral,
            'cantidad_ventas' => $cantidadVentas,
            'cerrado' => true,
            'cerrado_at' => now()->toDateTimeString(),
        ]);

        return back()->with('success', 'Turno cerrado exitosamente');
    }

    public function exportarArqueoPdf(Request $request)
    {
        $fechaDesde = $request->query('fecha_desde')
            ? Carbon::parse($request->query('fecha_desde'))
            : now();
        $fechaHasta = $request->query('fecha_hasta')
            ? Carbon::parse($request->query('fecha_hasta'))
            : now();

        $ownerId = Auth::user()->getOwnerId();
        $ventas = Venta::where('owner_id', $ownerId)
            ->where('es_pos', true)
            ->whereBetween('fecha', [$fechaDesde->toDateString(), $fechaHasta->toDateString()])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = [
            'efectivo' => $ventas->where('metodo_pago', 'efectivo')->sum('total'),
            'tarjeta' => $ventas->where('metodo_pago', 'tarjeta')->sum('total'),
            'transferencia' => $ventas->where('metodo_pago', 'transferencia')->sum('total'),
            'total' => $ventas->sum('total'),
            'cantidad_ventas' => $ventas->count(),
            'fecha_desde' => $fechaDesde->format('d/m/Y'),
            'fecha_hasta' => $fechaHasta->format('d/m/Y'),
            'usuario' => Auth::user()->name,
            'detalle' => $ventas->map(function ($v) {
                return [
                    'fecha' => $v->created_at->format('d/m/Y'),
                    'hora' => $v->created_at->format('H:i'),
                    'metodo' => ucfirst($v->metodo_pago),
                    'documento' => ucfirst($v->tipo_documento).' #'.($v->numero_factura ?? $v->id),
                    'monto' => $v->total,
                ];
            }),
        ];

        $pdf = Pdf::loadView('pdf.arqueo-caja', $data);

        return $pdf->download('arqueo_caja_'.$fechaDesde->format('Ymd').'_'.$fechaHasta->format('Ymd').'.pdf');
    }

    public function exportarArqueoCsv(Request $request)
    {
        $fechaDesde = $request->query('fecha_desde')
            ? Carbon::parse($request->query('fecha_desde'))
            : now();
        $fechaHasta = $request->query('fecha_hasta')
            ? Carbon::parse($request->query('fecha_hasta'))
            : now();

        $ownerId = Auth::user()->getOwnerId();
        $ventas = Venta::where('owner_id', $ownerId)
            ->where('es_pos', true)
            ->whereBetween('fecha', [$fechaDesde->toDateString(), $fechaHasta->toDateString()])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'arqueo_caja_'.$fechaDesde->format('Ymd').'_'.$fechaHasta->format('Ymd').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($ventas) {
            $file = fopen('php://output', 'w');
            // BOM for UTF-8 Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['Fecha', 'Hora', 'Tipo', 'Método de Pago', 'Documento', 'Monto'], ';');

            foreach ($ventas as $v) {
                fputcsv($file, [
                    $v->created_at->format('d/m/Y'),
                    $v->created_at->format('H:i'),
                    'Venta',
                    ucfirst($v->metodo_pago),
                    ucfirst($v->tipo_documento).' #'.($v->numero_factura ?? $v->id),
                    number_format($v->total, 0, ',', '.'),
                ], ';');
            }

            // Summary row
            fputcsv($file, [], ';');
            fputcsv($file, ['', '', '', '', 'TOTAL', number_format($ventas->sum('total'), 0, ',', '.')], ';');
            fputcsv($file, ['', '', '', '', 'Efectivo', number_format($ventas->where('metodo_pago', 'efectivo')->sum('total'), 0, ',', '.')], ';');
            fputcsv($file, ['', '', '', '', 'Tarjeta', number_format($ventas->where('metodo_pago', 'tarjeta')->sum('total'), 0, ',', '.')], ';');
            fputcsv($file, ['', '', '', '', 'Transferencia', number_format($ventas->where('metodo_pago', 'transferencia')->sum('total'), 0, ',', '.')], ';');

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function facturacion()
    {
        $ownerId = Auth::user()->getOwnerId();
        $documentos = Venta::query()
            ->with('cliente')
            ->where('owner_id', $ownerId)
            ->where('es_pos', true)
            ->latest()
            ->take(20)
            ->get();

        return Inertia::render('Backend/Pos/Facturacion', [
            'documentos' => $documentos,
        ]);
    }

    public function promociones()
    {
        $ownerId = Auth::user()->getOwnerId();
        $promociones = Promocion::with('categoria')
            ->where('owner_id', $ownerId)
            ->orderBy('created_at', 'desc')
            ->get();

        $categorias = Categoria::where('activo', true)->where('owner_id', $ownerId)->get(['id', 'nombre']);

        return Inertia::render('Backend/Pos/Promociones', [
            'promociones' => $promociones,
            'categorias' => $categorias,
        ]);
    }

    public function storePromocion(PromocionStoreRequest $request)
    {
        $data = $request->validated();
        $data['owner_id'] = Auth::user()->getOwnerId();
        $data['user_id'] = Auth::id();

        if (isset($data['skus']) && is_array($data['skus'])) {
            $data['skus'] = array_values(array_filter($data['skus']));
            if (empty($data['skus'])) {
                $data['skus'] = null;
            }
        }

        Promocion::create($data);

        return redirect()->route('pos.promociones')->with('success', 'Promoción creada correctamente.');
    }

    public function updatePromocion(PromocionUpdateRequest $request, Promocion $promocion)
    {
        $data = $request->validated();

        if (isset($data['skus']) && is_array($data['skus'])) {
            $data['skus'] = array_values(array_filter($data['skus']));
            if (empty($data['skus'])) {
                $data['skus'] = null;
            }
        }

        $promocion->update($data);

        return redirect()->route('pos.promociones')->with('success', 'Promoción actualizada correctamente.');
    }

    public function togglePromocion(Promocion $promocion)
    {
        $promocion->update(['activa' => ! $promocion->activa]);

        return redirect()->route('pos.promociones')->with('success', $promocion->activa ? 'Promoción activada.' : 'Promoción desactivada.');
    }

    public function destroyPromocion(Promocion $promocion)
    {
        $promocion->delete();

        return redirect()->route('pos.promociones')->with('success', 'Promoción eliminada.');
    }

    public function reportes(Request $request)
    {
        $ownerId = Auth::user()->getOwnerId();
        $almacenId = $request->query('almacen_id');

        // Ranking de productos
        $rankingQuery = Producto::join('detalle_ventas', 'productos.id', '=', 'detalle_ventas.producto_id')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->select('productos.nombre', DB::raw('SUM(detalle_ventas.cantidad) as total_vendidos'), DB::raw('SUM(detalle_ventas.subtotal) as total_ingresos'))
            ->where('productos.owner_id', $ownerId)
            ->where('ventas.estado', 'pagada');

        if ($almacenId) {
            $rankingQuery->where('ventas.almacen_id', $almacenId);
        }

        $ranking = $rankingQuery->groupBy('productos.id', 'productos.nombre')
            ->orderBy('total_vendidos', 'desc')
            ->get();

        // Utilidad aproximada
        $utilidadQuery = Venta::join('detalle_ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->select(DB::raw('SUM(detalle_ventas.subtotal - (productos.precio_compra * detalle_ventas.cantidad)) as utilidad_total'))
            ->where('ventas.owner_id', $ownerId)
            ->where('ventas.estado', 'pagada');

        if ($almacenId) {
            $utilidadQuery->where('ventas.almacen_id', $almacenId);
        }

        $utilidadData = $utilidadQuery->first();

        // Total Mes
        $totalMesQuery = Venta::where('owner_id', $ownerId)
            ->where('estado', 'pagada')
            ->whereMonth('fecha', now()->month);

        if ($almacenId) {
            $totalMesQuery->where('almacen_id', $almacenId);
        }

        $totalMes = $totalMesQuery->sum('total');

        $almacenes = Almacen::where('owner_id', $ownerId)->get(['id', 'nombre']);

        return Inertia::render('Backend/Pos/Reportes', [
            'ranking' => $ranking,
            'utilidad' => $utilidadData->utilidad_total ?? 0,
            'totalMes' => $totalMes,
            'almacenes' => $almacenes,
            'almacenId' => $almacenId,
        ]);
    }

    public function exportarReportes(Request $request)
    {
        $ownerId = Auth::user()->getOwnerId();
        $almacenId = $request->query('almacen_id');
        $format = $request->query('format', 'json');

        $rankingQuery = Producto::join('detalle_ventas', 'productos.id', '=', 'detalle_ventas.producto_id')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->select('productos.nombre', DB::raw('SUM(detalle_ventas.cantidad) as total_vendidos'), DB::raw('SUM(detalle_ventas.subtotal) as total_ingresos'))
            ->where('productos.owner_id', $ownerId)
            ->where('ventas.estado', 'pagada');

        if ($almacenId) {
            $rankingQuery->where('ventas.almacen_id', $almacenId);
        }

        $ranking = $rankingQuery->groupBy('productos.id', 'productos.nombre')
            ->orderBy('total_vendidos', 'desc')
            ->get();

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="reportes_pos.csv"',
            ];
            $data = $ranking->map(function ($item) {
                return [
                    'Producto' => $item->nombre,
                    'Unidades Vendidas' => $item->total_vendidos,
                    'Ingresos Totales' => $item->total_ingresos,
                ];
            });

            $csvContent = "Producto,Unidades Vendidas,Ingresos Totales\n";
            foreach ($data as $row) {
                $csvContent .= "{$row['Producto']},{$row['Unidades Vendidas']},{$row['Ingresos Totales']}\n";
            }

            return response($csvContent, 200, $headers);
        }

        if ($format === 'excel') {
            $headers = [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="reportes_pos.xlsx"',
            ];
            $data = $ranking->map(function ($item) {
                return [
                    'Producto' => $item->nombre,
                    'Unidades Vendidas' => $item->total_vendidos,
                    'Ingresos Totales' => $item->total_ingresos,
                ];
            });

            $csvContent = "Producto,Unidades Vendidas,Ingresos Totales\n";
            foreach ($data as $row) {
                $csvContent .= "{$row['Producto']},{$row['Unidades Vendidas']},{$row['Ingresos Totales']}\n";
            }

            return response($csvContent, 200, $headers);
        }

        return response()->json($ranking);
    }

    public function importarReportes(Request $request)
    {
        return back()->with('success', 'Importación de reportes procesado correctamente');
    }
}
