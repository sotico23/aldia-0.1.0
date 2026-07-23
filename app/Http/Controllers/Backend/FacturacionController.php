<?php

namespace App\Http\Controllers\Backend;

use App\Exports\FacturasExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\FiltraPorCliente;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\FacturasImport;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\DetalleFactura;
use App\Models\DetalleVenta;
use App\Models\Factura;
use App\Models\Producto;
use App\Models\Tesoreria;
use App\Models\Venta;
use App\Services\Sii\BarcodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FacturacionController extends Controller implements HasMiddleware
{
    use FiltraPorCliente, HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:finanzas.facturacion.create', only: ['create', 'store']),
            new Middleware('permission:finanzas.facturacion.edit', only: ['edit', 'update']),
            new Middleware('permission:finanzas.facturacion.delete', only: ['destroy']),
            new Middleware('permission:finanzas.facturacion.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:finanzas.facturacion.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(): Response
    {
        $ownerId = auth()->user()->getOwnerId();

        $query = Factura::with(['cliente', 'detalles.producto', 'emisor'])
            ->where('owner_id', $ownerId)
            ->orderBy('fecha', 'desc')
            ->orderBy('numero', 'desc');

        if ($this->usuarioEsCliente()) {
            $query->where('cliente_id', $this->getClienteAuth()->id);
        }

        $facturas = $query->paginate(15);
        $clientes = Cliente::where('activo', true)->where('owner_id', $ownerId)->orderBy('nombre')->get();
        $productos = Producto::where('activo', true)->where('owner_id', $ownerId)->with('inventarios')->get();
        $almacenes = Almacen::where('activo', true)->where('owner_id', $ownerId)->orderBy('nombre')->get();

        // Stats for charts
        $now = now();
        $mesActual = $now->format('Y-m');

        $totalFacturadoMes = (float) Factura::where('owner_id', $ownerId)
            ->where('estado', '!=', 'anulada')
            ->whereRaw("DATE_FORMAT(fecha, '%Y-%m') = ?", [$mesActual])
            ->sum('total');

        $totalPendiente = (float) Factura::where('owner_id', $ownerId)
            ->where('estado', 'pendiente')
            ->sum('total');

        $totalPagadoMes = (float) Factura::where('owner_id', $ownerId)
            ->where('estado', 'pagada')
            ->whereRaw("DATE_FORMAT(fecha, '%Y-%m') = ?", [$mesActual])
            ->sum('total');

        $facturasHoy = Factura::where('owner_id', $ownerId)
            ->whereDate('fecha', $now->toDateString())
            ->count();

        // Monthly trend: last 12 months (single grouped query instead of 12 individual queries)
        $doceMesesAtras = $now->copy()->subMonths(11)->startOfMonth()->format('Y-m-d');
        $trendData = Factura::where('owner_id', $ownerId)
            ->where('estado', '!=', 'anulada')
            ->where('fecha', '>=', $doceMesesAtras)
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total")
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->keyBy('mes');

        $tendenciaMensual = collect();
        for ($i = 11; $i >= 0; $i--) {
            $mes = $now->copy()->subMonths($i)->format('Y-m');
            $data = $trendData->get($mes);
            $tendenciaMensual->push([
                'mes' => $mes,
                'cantidad' => (int) ($data->cantidad ?? 0),
                'total' => (float) ($data->total ?? 0),
            ]);
        }

        // Distribution by estado
        $distribucionEstado = Factura::where('owner_id', $ownerId)
            ->selectRaw('estado, COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total')
            ->groupBy('estado')
            ->get()
            ->map(fn ($item) => [
                'estado' => $item->estado,
                'cantidad' => (int) $item->cantidad,
                'total' => (float) $item->total,
            ]);

        // Distribution by tipo
        $distribucionTipo = Factura::where('owner_id', $ownerId)
            ->selectRaw('tipo, COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total')
            ->groupBy('tipo')
            ->get()
            ->map(fn ($item) => [
                'tipo' => $item->tipo,
                'cantidad' => (int) $item->cantidad,
                'total' => (float) $item->total,
            ]);

        return Inertia::render('Backend/Facturacion/Index', [
            'facturas' => $facturas,
            'clientes' => $clientes,
            'productos' => $productos,
            'almacenes' => $almacenes,
            'stats' => [
                'total_facturado_mes' => $totalFacturadoMes,
                'total_pendiente' => $totalPendiente,
                'total_pagado_mes' => $totalPagadoMes,
                'facturas_hoy' => $facturasHoy,
                'tendencia_mensual' => $tendenciaMensual,
                'distribucion_estado' => $distribucionEstado,
                'distribucion_tipo' => $distribucionTipo,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isManual = $request->input('is_manual_cliente', false);

        $validated = $request->validate([
            'numero' => 'required|string|max:20|unique:facturas,numero',
            'cliente_id' => $isManual ? 'nullable' : 'required|exists:clientes,id',
            'fecha' => 'required|date',
            'fecha_vencimiento' => 'nullable|date',
            'tipo' => 'required|string|max:20|in:venta,compra,cotizacion,proforma',
            'notas' => 'nullable|string',
            'iva_porcentaje' => 'nullable|numeric|min:0',
            'iva_incluido' => 'nullable|boolean',
            'descuento_tipo' => 'nullable|string|in:none,porcentaje,monto',
            'descuento_valor' => 'nullable|numeric|min:0',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|exists:productos,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',

            // Validación para cliente manual si aplica
            'manual_rut' => $isManual ? 'required|string' : 'nullable',
            'manual_razon_social' => $isManual ? 'required|string' : 'nullable',
            'manual_giro' => $isManual ? 'required|string' : 'nullable',
            'manual_direccion' => $isManual ? 'required|string' : 'nullable',
            'manual_comuna' => $isManual ? 'required|string' : 'nullable',
            'manual_ciudad' => $isManual ? 'nullable|string' : 'nullable',
        ]);

        $clienteId = $validated['cliente_id'];

        if ($isManual) {
            $rut = $validated['manual_rut'];
            $cliente = Cliente::where('rut', $rut)
                ->where('owner_id', auth()->user()->getOwnerId())
                ->first();

            if (! $cliente) {
                $cliente = Cliente::create([
                    'owner_id' => auth()->user()->getOwnerId(),
                    'user_id' => auth()->id(),
                    'rut' => $rut,
                    'nombre' => $validated['manual_razon_social'],
                    'giro' => $validated['manual_giro'],
                    'direccion' => $validated['manual_direccion'],
                    'comuna' => $validated['manual_comuna'],
                    'ciudad' => $validated['manual_ciudad'] ?? null,
                    'activo' => true,
                ]);
            }
            $clienteId = $cliente->id;
        }

        $ivaPorcentaje = $validated['iva_porcentaje'] ?? 19;
        $ivaIncluido = $validated['iva_incluido'] ?? true;
        $descuentoTipo = $validated['descuento_tipo'] ?? 'none';
        $descuentoValor = $validated['descuento_valor'] ?? 0;

        $totalBruto = 0;
        foreach ($validated['detalles'] as $detalle) {
            $totalBruto += $detalle['cantidad'] * $detalle['precio_unitario'];
        }

        $totalDescuento = 0;
        if ($descuentoTipo === 'porcentaje') {
            $totalDescuento = round($totalBruto * ($descuentoValor / 100), 0);
        } elseif ($descuentoTipo === 'monto') {
            $totalDescuento = round($descuentoValor, 0);
        }

        $montoFinal = round($totalBruto - $totalDescuento, 0);

        if ($ivaIncluido) {
            $total = $montoFinal;
            $subtotal = round($total / (1 + ($ivaPorcentaje / 100)), 0);
            $impuesto = $total - $subtotal;
        } else {
            $subtotal = $montoFinal;
            $impuesto = round($subtotal * ($ivaPorcentaje / 100), 0);
            $total = $subtotal + $impuesto;
        }

        $factura = Factura::create([
            'numero' => $validated['numero'],
            'owner_id' => auth()->user()->getOwnerId(),
            'user_id' => auth()->id(),
            'cliente_id' => $clienteId,
            'fecha' => $validated['fecha'],
            'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
            'tipo' => $validated['tipo'],
            'subtotal' => $subtotal, // Neto
            'impuesto' => $impuesto, // IVA
            'total' => $total,
            'iva_porcentaje' => $ivaPorcentaje,
            'iva_incluido' => $ivaIncluido,
            'descuento_tipo' => $descuentoTipo,
            'descuento_valor' => $descuentoValor,
            'total_descuento' => $totalDescuento,
            'almacen_id' => $validated['almacen_id'],
            'estado' => 'pendiente',
            'notas' => $validated['notas'] ?? null,
        ]);

        foreach ($validated['detalles'] as $detalle) {
            $itemTotalBruto = $detalle['cantidad'] * $detalle['precio_unitario'];

            // Proportional discount if applicable
            $itemDescuento = $totalBruto > 0 ? ($itemTotalBruto / $totalBruto) * $totalDescuento : 0;
            $itemMontoFinal = $itemTotalBruto - $itemDescuento;

            if ($ivaIncluido) {
                $itemTotal = round($itemMontoFinal, 0);
                $itemNeto = round($itemTotal / (1 + ($ivaPorcentaje / 100)), 0);
                $itemIVA = $itemTotal - $itemNeto;
            } else {
                $itemNeto = round($itemMontoFinal, 0);
                $itemIVA = round($itemNeto * ($ivaPorcentaje / 100), 0);
                $itemTotal = $itemNeto + $itemIVA;
            }

            $factura->detalles()->create([
                'producto_id' => $detalle['producto_id'],
                'cantidad' => $detalle['cantidad'],
                'precio_unitario' => $detalle['precio_unitario'],
                'subtotal' => $itemNeto,
                'impuesto' => $itemIVA,
                'total' => $itemTotal,
            ]);
        }

        return redirect()->route('facturacion.index');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $factura = Factura::findOrFail($id);

        $validated = $request->validate([
            'numero' => 'required|string|unique:facturas,numero,'.$factura->id,
            'cliente_id' => 'required|exists:clientes,id',
            'fecha' => 'required|date',
            'fecha_vencimiento' => 'nullable|date',
            'tipo' => 'required|string|in:venta,compra,cotizacion,proforma',
            'notas' => 'nullable|string',
            'iva_porcentaje' => 'required|numeric|min:0',
            'iva_incluido' => 'required|boolean',
            'descuento_tipo' => 'required|string|in:none,porcentaje,monto',
            'descuento_valor' => 'required|numeric|min:0',
            'almacen_id' => 'nullable|exists:almacenes,id',
            'estado' => 'required|string|in:pendiente,pagada,anulada',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
        ]);

        $oldEstado = $factura->estado;
        $newEstado = $validated['estado'];

        try {
            DB::transaction(function () use ($factura, $validated, $oldEstado, $newEstado) {
                $ivaPorcentaje = $validated['iva_porcentaje'];
                $ivaIncluido = $validated['iva_incluido'];
                $descuentoTipo = $validated['descuento_tipo'];
                $descuentoValor = $validated['descuento_valor'];

                $totalBruto = 0;
                foreach ($validated['detalles'] as $detalle) {
                    $totalBruto += $detalle['cantidad'] * $detalle['precio_unitario'];
                }

                $totalDescuento = 0;
                if ($descuentoTipo === 'porcentaje') {
                    $totalDescuento = round($totalBruto * ($descuentoValor / 100), 0);
                } elseif ($descuentoTipo === 'monto') {
                    $totalDescuento = round($descuentoValor, 0);
                }

                $montoFinal = round($totalBruto - $totalDescuento, 0);

                if ($ivaIncluido) {
                    $total = $montoFinal;
                    $subtotal = round($total / (1 + ($ivaPorcentaje / 100)), 0);
                    $impuesto = $total - $subtotal;
                } else {
                    $subtotal = $montoFinal;
                    $impuesto = round($subtotal * ($ivaPorcentaje / 100), 0);
                    $total = $subtotal + $impuesto;
                }

                $factura->update([
                    'numero' => $validated['numero'],
                    'cliente_id' => $validated['cliente_id'],
                    'fecha' => $validated['fecha'],
                    'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                    'tipo' => $validated['tipo'],
                    'subtotal' => $subtotal,
                    'impuesto' => $impuesto,
                    'total' => $total,
                    'iva_porcentaje' => $ivaPorcentaje,
                    'iva_incluido' => $ivaIncluido,
                    'descuento_tipo' => $descuentoTipo,
                    'descuento_valor' => $descuentoValor,
                    'total_descuento' => $totalDescuento,
                    'almacen_id' => $validated['almacen_id'],
                    'estado' => $newEstado,
                    'notas' => $validated['notas'] ?? null,
                ]);

                $factura->refresh();
                $facturaId = $factura->id;
                Log::info('facturaId antes de delete:', ['facturaId' => $facturaId, 'tipo' => gettype($facturaId)]);
                DetalleFactura::where('factura_id', $facturaId)->delete();

                foreach ($validated['detalles'] as $idx => $detalle) {
                    $itemTotalBruto = $detalle['cantidad'] * $detalle['precio_unitario'];
                    $itemDescuento = $totalBruto > 0 ? ($itemTotalBruto / $totalBruto) * $totalDescuento : 0;
                    $itemMontoFinal = $itemTotalBruto - $itemDescuento;

                    if ($ivaIncluido) {
                        $itemTotal = round($itemMontoFinal, 0);
                        $itemNeto = round($itemTotal / (1 + ($ivaPorcentaje / 100)), 0);
                        $itemIVA = $itemTotal - $itemNeto;
                    } else {
                        $itemNeto = round($itemMontoFinal, 0);
                        $itemIVA = round($itemNeto * ($ivaPorcentaje / 100), 0);
                        $itemTotal = $itemNeto + $itemIVA;
                    }

                    $dataToCreate = [
                        'factura_id' => (int) $facturaId,
                        'producto_id' => (int) $detalle['producto_id'],
                        'cantidad' => (int) $detalle['cantidad'],
                        'precio_unitario' => (float) $detalle['precio_unitario'],
                        'subtotal' => (float) $itemNeto,
                        'impuesto' => (float) $itemIVA,
                        'total' => (float) $itemTotal,
                    ];
                    Log::info('Creando detalle '.$idx.':', $dataToCreate);
                    DetalleFactura::create($dataToCreate);
                }

                if ($oldEstado !== 'pagada' && $newEstado === 'pagada') {
                    $factura->load(['cliente', 'detalles.producto']);

                    $venta = Venta::create([
                        'numero' => $factura->numero,
                        'owner_id' => $factura->owner_id,
                        'cliente_id' => $factura->cliente_id,
                        'user_id' => auth()->id(),
                        'fecha' => now(),
                        'subtotal' => $factura->subtotal,
                        'iva' => $factura->impuesto,
                        'total' => $factura->total,
                        'metodo_pago' => 'transferencia',
                        'tipo_documento' => 'factura',
                        'es_pos' => false,
                        'estado' => 'pagada',
                        'notas' => $factura->notas,
                    ]);

                    foreach ($factura->detalles as $detalle) {
                        DetalleVenta::create([
                            'venta_id' => $venta->id,
                            'producto_id' => $detalle->producto_id,
                            'cantidad' => $detalle->cantidad,
                            'precio_unitario' => $detalle->precio_unitario,
                            'subtotal' => $detalle->subtotal,
                        ]);

                        $producto = $detalle->producto;
                        if ($producto) {
                            $stock = $producto->inventarios()->where('almacen_id', $factura->almacen_id)->first();
                            if ($stock) {
                                $stock->decrement('cantidad', $detalle->cantidad);
                            } else {
                                $producto->inventarios()->create([
                                    'almacen_id' => $factura->almacen_id ?? 1,
                                    'cantidad' => -$detalle->cantidad,
                                    'cantidad_minima' => 0,
                                ]);
                            }
                        }
                    }

                    Tesoreria::create([
                        'owner_id' => $factura->owner_id,
                        'tipo' => 'ingreso',
                        'monto' => $factura->total,
                        'descripcion' => 'Pago de factura #'.$factura->numero.' - Cliente: '.($factura->cliente?->nombre ?? 'N/A'),
                        'fecha' => now(),
                        'referencia' => 'FACT-'.$factura->numero,
                        'categoria' => 'ventas',
                        'estado' => 'confirmado',
                    ]);
                }
            });

            return redirect()->route('facturacion.index')->with('success', 'Factura actualizada correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('facturacion.index')->with('error', 'Error al actualizar factura: '.$e->getMessage());
        }
    }

    public function downloadPdf(Factura $factura): SymfonyResponse
    {
        $factura->load(['cliente', 'detalles.producto', 'emisor', 'dteDocumento']);

        $barcodeSvg = null;
        if ($factura->dteDocumento && $factura->dteDocumento->ted) {
            try {
                $barcodeService = app(BarcodeService::class);
                $barcodeSvg = $barcodeService->generarPdf417($factura->dteDocumento->ted);
            } catch (\Exception $e) {
                Log::error('Error al generar barcode para PDF: '.$e->getMessage());
            }
        }

        $pdf = Pdf::loadView('pdf.billing_pdf', [
            'factura' => $factura,
            'dte' => $factura->dteDocumento,
            'barcodeSvg' => $barcodeSvg,
        ])->setPaper('a4');

        $filename = $factura->dteDocumento
            ? "DTE-{$factura->dteDocumento->tipo_documento}-{$factura->dteDocumento->folio}.pdf"
            : "{$factura->tipo}-{$factura->numero}.pdf";

        return $pdf->download($filename);
    }

    protected function getExportClass(array $filters): object
    {
        return new FacturasExport($filters);
    }

    protected function getImportClass(): object
    {
        return new FacturasImport;
    }
}
