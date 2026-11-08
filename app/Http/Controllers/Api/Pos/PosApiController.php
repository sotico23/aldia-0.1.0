<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DeliveryRate;
use App\Models\DetalleVenta;
use App\Models\Entrega;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\PosCierreCaja;
use App\Models\Producto;
use App\Models\SkuVariante;
use App\Models\Tesoreria;
use App\Models\Vacio;
use App\Models\Venta;
use App\Services\Inventory\SalesInventoryService;
use App\Services\PedidoPoolService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PosApiController extends Controller
{
    public function __construct(
        protected SalesInventoryService $salesInventoryService
    ) {}

    /**
     * GET /api/v1/pos/catalog
     * Catálogo completo para el punto de venta móvil: productos, variantes, stock, categorías, tarifas y medios de pago.
     */
    public function catalog(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownerId = $user->getOwnerId();

        $categorias = Categoria::where('owner_id', $ownerId)
            ->where('activo', true)
            ->withCount('productos')
            ->get(['id', 'nombre', 'descripcion', 'imagen', 'activo']);

        $productos = Producto::where('owner_id', $ownerId)
            ->where('activo', true)
            ->with([
                'categoria:id,nombre',
                'skus' => fn ($q) => $q->where('activo', true)->with('valores'),
                'inventarios.almacen:id,nombre',
            ])
            ->get()
            ->map(function (Producto $p) {
                $stockTotal = (float) $p->inventarios->sum('cantidad');

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'codigo' => $p->codigo ?? '',
                    'descripcion' => $p->descripcion ?? '',
                    'categoria_id' => $p->categoria_id,
                    'categoria_nombre' => $p->categoria?->nombre ?? 'General',
                    'precio_venta' => (float) $p->precio_venta,
                    'precio_compra' => (float) ($p->precio_compra ?? 0),
                    'stock_minimo' => (float) ($p->stock_minimo ?? 0),
                    'stock_actual' => $stockTotal,
                    'medida_pesable' => (bool) $p->medida_pesable,
                    'unidad_medida' => $p->unidad_medida ?? 'unidad',
                    'envase_retornable' => (bool) $p->envase_retornable,
                    'envase_producto_id' => $p->envase_producto_id,
                    'imagen' => $p->imagen,
                    'tiene_variantes' => (bool) $p->tiene_variantes,
                    'variantes' => $p->skus->map(fn ($sku) => [
                        'id' => $sku->id,
                        'sku' => $sku->sku,
                        'precio_venta' => (float) $sku->precio_venta,
                        'stock' => (float) $sku->stock,
                        'activo' => (bool) $sku->activo,
                    ])->values()->all(),
                ];
            });

        $deliveryRates = DeliveryRate::where('owner_id', $ownerId)
            ->where('is_active', true)
            ->get()
            ->map(fn ($rate) => [
                'id' => $rate->id,
                'zone_id' => $rate->zone_id,
                'calculation_type' => $rate->calculation_type,
                'commune' => $rate->commune ?? 'General',
                'km_from' => (float) ($rate->km_from ?? 0),
                'km_to' => (float) ($rate->km_to ?? 0),
                'cost' => (float) $rate->cost,
            ]);

        $almacenes = Almacen::where('owner_id', $ownerId)
            ->get(['id', 'nombre', 'direccion']);

        return response()->json([
            'success' => true,
            'categorias' => $categorias,
            'productos' => $productos,
            'delivery_rates' => $deliveryRates,
            'almacenes' => $almacenes,
            'dispatch_modes' => [
                [
                    'id' => Pedido::DISPATCH_INTERNAL,
                    'label' => 'Repartidores Propios',
                    'description' => 'Derivar exclusivamente a los repartidores propios de la empresa.',
                ],
                [
                    'id' => Pedido::DISPATCH_MARKETPLACE,
                    'label' => 'Red Abierta / Freelancers',
                    'description' => 'Liberar el pedido al marketplace para repartidores independientes tipo Uber/Rappi.',
                ],
            ],
            'metodos_pago' => [
                ['id' => 'efectivo', 'label' => 'Efectivo'],
                ['id' => 'tarjeta', 'label' => 'Débito / Crédito'],
                ['id' => 'transferencia', 'label' => 'Transferencia Bancaria'],
                ['id' => 'vale', 'label' => 'Vale de Gas'],
                ['id' => 'visa_transbank', 'label' => 'Transbank'],
            ],
        ]);
    }

    /**
     * GET /api/v1/pos/stock
     * Consulta de existencias en tiempo real por producto o almacén.
     */
    public function stock(Request $request): JsonResponse
    {
        $ownerId = $request->user()->getOwnerId();
        $query = Inventario::where('owner_id', $ownerId)->with(['producto:id,nombre,codigo,unidad_medida', 'almacen:id,nombre']);

        if ($request->filled('producto_id')) {
            $query->where('producto_id', $request->input('producto_id'));
        }

        if ($request->filled('almacen_id')) {
            $query->where('almacen_id', $request->input('almacen_id'));
        }

        $stock = $query->get()->map(fn ($inv) => [
            'id' => $inv->id,
            'producto_id' => $inv->producto_id,
            'producto_nombre' => $inv->producto?->nombre ?? 'N/A',
            'codigo' => $inv->producto?->codigo ?? '',
            'unidad_medida' => $inv->producto?->unidad_medida ?? 'unidad',
            'almacen_id' => $inv->almacen_id,
            'almacen_nombre' => $inv->almacen?->nombre ?? 'N/A',
            'cantidad' => (float) $inv->cantidad,
            'cantidad_minima' => (float) ($inv->cantidad_minima ?? 0),
        ]);

        return response()->json([
            'success' => true,
            'stock' => $stock,
        ]);
    }

    /**
     * POST /api/v1/pos/sales
     * Registro de venta rápida desde móvil/tablet con descuento atómico de inventario,
     * registro de envases retornables y opción de entrega delivery.
     */
    public function storeSale(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownerId = $user->getOwnerId();

        $validated = $request->validate([
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('owner_id', $ownerId)],
            'metodo_pago' => 'required|in:efectivo,tarjeta,transferencia,vale,visa_transbank,binance,contactar',
            'tipo_documento' => 'required|in:boleta,factura',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => ['required', Rule::exists('productos', 'id')->where('owner_id', $ownerId)],
            'items.*.sku_variante_id' => ['nullable', Rule::exists('sku_variantes', 'id')->where('owner_id', $ownerId)],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'items.*.precio' => ['required', 'numeric', 'min:0'],
            'items.*.cantidad_retornada' => ['nullable', 'integer', 'min:0'],
            'subtotal' => 'required|numeric',
            'iva' => 'required|numeric',
            'total' => 'required|numeric',
            'descuento' => 'nullable|numeric|min:0',
            'almacen_id' => ['nullable', Rule::exists('almacenes', 'id')->where('owner_id', $ownerId)],
            'has_delivery' => ['sometimes', 'boolean'],
            'dispatch_mode' => ['nullable', 'string', 'in:internal,marketplace'],
            'delivery_type' => ['nullable', 'string', 'in:internal,marketplace'],
            'delivery_rate_id' => ['nullable', 'integer', Rule::exists('delivery_rates', 'id')->where('owner_id', $ownerId)],
            'delivery_cost' => ['nullable', 'numeric', 'min:0'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'delivery_recipient_name' => ['nullable', 'string', 'max:255'],
            'delivery_phone' => ['nullable', 'string', 'max:50'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $venta = DB::transaction(function () use ($validated, $ownerId, $user, $request) {
                $cliente = ! empty($validated['cliente_id'])
                    ? Cliente::where('owner_id', $ownerId)->find($validated['cliente_id'])
                    : null;

                $almacenId = $validated['almacen_id'] ?? Almacen::where('owner_id', $ownerId)->value('id') ?? 1;
                $dispatchMode = $validated['dispatch_mode'] ?? $validated['delivery_type'] ?? Pedido::DISPATCH_INTERNAL;

                // 1. Crear Venta principal
                $venta = Venta::create([
                    'owner_id' => $ownerId,
                    'user_id' => $user->id,
                    'cliente_id' => $cliente?->id,
                    'fecha' => now()->toDateString(),
                    'hora' => now()->format('H:i:s'),
                    'tipo_documento' => $validated['tipo_documento'],
                    'numero_factura' => 'POS-' . strtoupper(Str::random(6)),
                    'subtotal' => $validated['subtotal'],
                    'iva' => $validated['iva'],
                    'descuento' => $validated['descuento'] ?? 0,
                    'total' => $validated['total'],
                    'metodo_pago' => $validated['metodo_pago'],
                    'estado_pago' => 'pagado',
                    'has_delivery' => $request->boolean('has_delivery'),
                    'dispatch_mode' => $dispatchMode,
                    'estado_entrega' => $request->boolean('has_delivery') ? 'pendiente' : 'entregado',
                    'es_pos' => true,
                    'notas' => 'Venta registrada desde AlDia Móvil POS',
                ]);

                // 2. Crear detalles de venta y decrementar SKUs
                $itemsForInventory = [];

                foreach ($validated['items'] as $item) {
                    $producto = Producto::find($item['producto_id']);
                    $sku = ! empty($item['sku_variante_id']) ? SkuVariante::find($item['sku_variante_id']) : null;
                    $cant = (float) $item['cantidad'];
                    $precio = (float) $item['precio'];
                    $subtotalItem = $cant * $precio;

                    DetalleVenta::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $producto->id,
                        'sku_variante_id' => $sku?->id,
                        'cantidad' => $cant,
                        'precio_unitario' => $precio,
                        'subtotal' => $subtotalItem,
                        'cantidad_retornada' => $item['cantidad_retornada'] ?? null,
                    ]);

                    if ($sku) {
                        $sku->decrement('stock', $cant);
                    }

                    $itemsForInventory[] = [
                        'producto_id' => $producto->id,
                        'cantidad' => $cant,
                        'cantidad_retornada' => (int) ($item['cantidad_retornada'] ?? 0),
                        'almacen_id' => $almacenId,
                    ];
                }

                // 3. Procesar inventario con lock atómico y envases retornables
                $this->salesInventoryService->processSaleInventory(
                    venta: $venta,
                    items: $itemsForInventory,
                    ownerId: $ownerId,
                    userId: $user->id,
                    almacenIds: [$almacenId]
                );

                // 4. Registro en Tesorería
                Tesoreria::create([
                    'owner_id' => $ownerId,
                    'tipo' => 'ingreso',
                    'monto' => $validated['total'],
                    'metodo_pago' => $validated['metodo_pago'],
                    'origen' => 'venta_pos',
                    'descripcion' => "Venta POS Móvil #{$venta->id}",
                    'fecha' => now()->toDateString(),
                ]);

                // 5. Si incluye delivery, generar Entrega / Pedido para el pool de repartidores
                if ($request->boolean('has_delivery')) {
                    $pedido = Pedido::create([
                        'owner_id' => $ownerId,
                        'user_id' => $user->id,
                        'cliente_id' => $cliente?->id,
                        'numero_pedido' => 'POS-DEL-' . $venta->id,
                        'estado' => 'preparando',
                        'dispatch_mode' => $dispatchMode,
                        'delivery_type' => $dispatchMode,
                        'subtotal' => $validated['subtotal'],
                        'impuesto' => $validated['iva'],
                        'total' => $validated['total'],
                        'costo_envio' => (float) ($validated['delivery_cost'] ?? 0),
                        'metodo_pago' => $validated['metodo_pago'],
                        'nombre_cliente' => $validated['delivery_recipient_name'] ?? $cliente?->nombre ?? 'Cliente POS',
                        'telefono_cliente' => $validated['delivery_phone'] ?? $cliente?->telefono ?? '',
                        'direccion_cliente' => $validated['delivery_address'] ?? $cliente?->direccion ?? 'Entrega POS',
                        'notas' => $validated['delivery_notes'] ?? 'Despacho generado desde POS Móvil',
                        'pool_entrada_at' => now(),
                    ]);

                    foreach ($validated['items'] as $item) {
                        $producto = Producto::find($item['producto_id']);
                        PedidoItem::create([
                            'owner_id' => $ownerId,
                            'pedido_id' => $pedido->id,
                            'producto_id' => $producto->id,
                            'nombre_producto' => $producto->nombre,
                            'precio_unitario' => (float) $item['precio'],
                            'cantidad' => (float) $item['cantidad'],
                            'subtotal' => ((float) $item['cantidad']) * ((float) $item['precio']),
                        ]);
                    }

                    Entrega::create([
                        'owner_id' => $ownerId,
                        'venta_id' => $venta->id,
                        'cliente_id' => $cliente?->id,
                        'cliente' => $validated['delivery_recipient_name'] ?? $cliente?->nombre ?? 'Cliente POS',
                        'telefono' => $validated['delivery_phone'] ?? $cliente?->telefono,
                        'direccion' => $validated['delivery_address'] ?? $cliente?->direccion,
                        'estado' => 'pendiente',
                        'fecha' => now()->toDateString(),
                        'notas' => 'Entrega vinculada a Pedido #' . $pedido->numero_pedido . ' (' . ($dispatchMode === Pedido::DISPATCH_MARKETPLACE ? 'Marketplace' : 'Interno') . ')',
                    ]);

                    // Notificar al pool de repartidores
                    app(PedidoPoolService::class)->ingresarAlPool($pedido);
                }

                return $venta;
            });

            return response()->json([
                'success' => true,
                'message' => 'Venta POS registrada con éxito e inventario actualizado.',
                'data' => [
                    'venta_id' => $venta->id,
                    'numero_factura' => $venta->numero_factura,
                    'total' => (float) $venta->total,
                    'metodo_pago' => $venta->metodo_pago,
                    'has_delivery' => (bool) $venta->has_delivery,
                    'dispatch_mode' => $venta->dispatch_mode ?? 'internal',
                    'fecha' => $venta->fecha,
                    'hora' => $venta->hora,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error registrando venta POS móvil: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la venta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/pos/caja/status
     * Consulta el estado de ventas del turno de hoy para el cajero.
     */
    public function cajaStatus(Request $request): JsonResponse
    {
        $ownerId = $request->user()->getOwnerId();
        $hoy = now()->toDateString();

        $ventas = Venta::where('owner_id', $ownerId)
            ->where('es_pos', true)
            ->whereDate('created_at', $hoy)
            ->get();

        $totalEfectivo = (float) $ventas->where('metodo_pago', 'efectivo')->sum('total');
        $totalTarjeta = (float) $ventas->whereIn('metodo_pago', ['tarjeta', 'visa_transbank'])->sum('total');
        $totalTransferencia = (float) $ventas->where('metodo_pago', 'transferencia')->sum('total');
        $totalVales = (float) $ventas->where('metodo_pago', 'vale')->sum('total');
        $totalGeneral = (float) $ventas->sum('total');

        $cierreHoy = PosCierreCaja::where('owner_id', $ownerId)
            ->whereDate('fecha_hasta', $hoy)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'fecha' => $hoy,
                'total_efectivo' => $totalEfectivo,
                'total_tarjeta' => $totalTarjeta,
                'total_transferencia' => $totalTransferencia,
                'total_vales' => $totalVales,
                'total_general' => $totalGeneral,
                'cantidad_ventas' => $ventas->count(),
                'caja_cerrada' => $cierreHoy !== null,
                'ultimo_cierre' => $cierreHoy ? [
                    'id' => $cierreHoy->id,
                    'cerrado_at' => $cierreHoy->created_at?->toISOString(),
                    'total_general' => (float) $cierreHoy->total_general,
                ] : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/pos/caja/cerrar
     * Realiza el cierre de turno / arqueo de caja del cajero.
     */
    public function cerrarCaja(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownerId = $user->getOwnerId();
        $hoy = now()->toDateString();

        $ventas = Venta::where('owner_id', $ownerId)
            ->where('es_pos', true)
            ->whereDate('created_at', $hoy)
            ->get();

        $totalEfectivo = (float) $ventas->where('metodo_pago', 'efectivo')->sum('total');
        $totalTarjeta = (float) $ventas->whereIn('metodo_pago', ['tarjeta', 'visa_transbank'])->sum('total');
        $totalTransferencia = (float) $ventas->where('metodo_pago', 'transferencia')->sum('total');
        $totalOtros = (float) $ventas->whereNotIn('metodo_pago', ['efectivo', 'tarjeta', 'visa_transbank', 'transferencia'])->sum('total');
        $totalGeneral = (float) $ventas->sum('total');

        $cierre = PosCierreCaja::create([
            'owner_id' => $ownerId,
            'imported_by' => $user->id,
            'fecha_desde' => $hoy,
            'fecha_hasta' => $hoy,
            'total_efectivo' => $totalEfectivo,
            'total_tarjeta' => $totalTarjeta,
            'total_transferencia' => $totalTransferencia,
            'total_otros' => $totalOtros,
            'total_general' => $totalGeneral,
            'cantidad_ventas' => $ventas->count(),
            'referencia' => 'Cierre Turno Móvil ' . now()->format('Y-m-d H:i'),
            'importado_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cierre de caja completado exitosamente.',
            'cierre' => $cierre,
        ]);
    }
}
