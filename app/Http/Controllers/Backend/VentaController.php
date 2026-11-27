<?php

namespace App\Http\Controllers\Backend;

use App\Enums\Currency;
use App\Exports\VentasExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\FiltraPorCliente;
use App\Imports\VentasImport;
use App\Livewire\Ventas\Index as VentasIndex;
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
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
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

    public function index(Request $request): Response
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('ventas.index'));
        }

        config(['livewire.csp_safe' => true]);

        return app(VentasIndex::class)();
    }

    public function store(Request $request): RedirectResponse
    {
        $this->crearVenta($request);

        return redirect()->route('ventas.index');
    }

    public function crearVenta(Request $request): Venta
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
            throw ValidationException::withMessages(['cliente_id' => 'Debe seleccionar un cliente o ingresar datos del cliente genérico']);
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
                throw ValidationException::withMessages(['cupon_codigo' => 'El cupón ingresado no es válido o ha expirado.']);
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

        return DB::transaction(function () use ($ventaData, $almacenIds, $validated, $cuponId, $cupon, $montoCupon, $total) {
            // Canjear cupón dentro de la transacción (atómico)
            if ($cupon && $cuponId) {
                if (! $cupon->canjear(auth()->id())) {
                    throw ValidationException::withMessages(['cupon_codigo' => 'El cupón no pudo ser canjeado. Verifique que no haya alcanzado el límite de usos.']);
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
        $this->actualizarVenta($request, $venta);

        return redirect()->route('ventas.index');
    }

    public function actualizarVenta(Request $request, Venta $venta): void
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
    }

    public function destroy(Venta $venta): RedirectResponse
    {
        $this->eliminarVenta($venta);

        return redirect()->route('ventas.index');
    }

    public function eliminarVenta(Venta $venta): void
    {
        $this->authorize('delete', $venta);

        if ($this->usuarioEsCliente() && $venta->cliente_id !== $this->getClienteAuth()->id) {
            abort(403, 'No tienes permiso para eliminar esta venta.');
        }

        if ($venta->estado === 'pagada') {
            $this->restaurarInventario($venta);
        }

        $venta->delete();
    }

    public function updateStatus(Request $request, Venta $venta): RedirectResponse
    {
        $this->cambiarEstado($request, $venta);

        return redirect()->back();
    }

    public function cambiarEstado(Request $request, Venta $venta): void
    {
        $this->authorize('update', $venta);

        $validated = $request->validate([
            'estado' => 'required|in:pendiente,pagada,cancelada',
        ]);

        DB::transaction(function () use ($venta, $validated): void {
            $venta = Venta::query()->lockForUpdate()->findOrFail($venta->id);
            $estadoAnterior = $venta->estado;
            $nuevoEstado = $validated['estado'];

            if ($estadoAnterior === $nuevoEstado) {
                return;
            }

            $venta->update(['estado' => $nuevoEstado]);

            if ($nuevoEstado === 'pagada') {
                $this->procesarPago($venta);
            }

            if ($estadoAnterior === 'pagada') {
                $this->restaurarInventario($venta);
            }
        });
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

    /** Vista imprimible en papel térmico de 80 mm (impresora del navegador). */
    public function printTicket(Venta $venta): View
    {
        $venta->load(['cliente', 'detalleVentas.producto', 'almacen']);

        return view('livewire.ventas.ticket-termico', [
            'venta' => $venta,
            'emisor' => auth()->user(),
            'documento' => $venta->folio ?: $venta->numero,
        ]);
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
        return redirect()->back()->with('success', $this->importarVentas($request));
    }

    public function importarVentas(Request $request): string
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

        return $message;
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
            $numeroAsiento = Asiento::numeroParaVenta($venta);
            if ($numeroAsiento !== null) {
                $asiento = Asiento::create([
                    'owner_id' => $venta->owner_id,
                    'fecha' => now(),
                    'numero' => $numeroAsiento,
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
