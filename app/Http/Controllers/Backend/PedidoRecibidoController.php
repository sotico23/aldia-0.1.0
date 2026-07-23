<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\NotificationHelper;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\DetalleVenta;
use App\Models\Pedido;
use App\Models\Tesoreria;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\ActualizacionEstadoPedidoNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PedidoRecibidoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Pedido::class);

        $userId = Auth::id();
        $query = Pedido::query()
            ->with(['cliente', 'items', 'conversacion'])
            ->where('user_id', $userId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('numero_pedido', 'like', "%{$search}%")
                    ->orWhere('nombre_cliente', 'like', "%{$search}%")
                    ->orWhereHas('cliente', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('estado') && $request->input('estado') !== 'all') {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('fechaDesde')) {
            $query->whereDate('created_at', '>=', $request->input('fechaDesde'));
        }

        if ($request->filled('fechaHasta')) {
            $query->whereDate('created_at', '<=', $request->input('fechaHasta'));
        }

        $pedidos = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('Backend/PedidosRecibidos/Index', [
            'pedidos' => $pedidos,
            'filtros' => $request->only(['search', 'estado', 'fechaDesde', 'fechaHasta']),
        ]);
    }

    public function show(Pedido $pedido): Response
    {
        $this->authorize('view', $pedido);

        $pedido->load('items', 'cliente', 'conversacion.mensajes.sender');

        return Inertia::render('Backend/PedidosRecibidos/Show', [
            'pedido' => $pedido,
        ]);
    }

    public function export(Request $request)
    {
        $userId = Auth::id();
        $query = Pedido::query()
            ->with(['cliente', 'items'])
            ->where('user_id', $userId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('numero_pedido', 'like', "%{$search}%")
                    ->orWhere('nombre_cliente', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado') && $request->input('estado') !== 'all') {
            $query->where('estado', $request->input('estado'));
        }

        $pedidos = $query->orderBy('created_at', 'desc')->get();

        $format = $request->input('format', 'csv');

        $filename = 'pedidos_recibidos_'.date('Y-m-d_His');

        if ($format === 'excel') {
            $headers = ['N° Pedido', 'Fecha', 'Cliente', 'Teléfono', 'Dirección', 'Total', 'Estado'];
            $rows = $pedidos->map(function ($p) {
                return [
                    $p->numero_pedido,
                    $p->created_at->format('Y-m-d H:i'),
                    $p->nombre_cliente,
                    $p->telefono_cliente ?? '',
                    $p->direccion_cliente ?? '',
                    $p->total,
                    $p->estado,
                ];
            });

            return response()->streamDownload(function () use ($headers, $rows) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
                fputcsv($handle, $headers, ';');
                foreach ($rows as $row) {
                    fputcsv($handle, $row, ';');
                }
                fclose($handle);
            }, $filename.'.csv', ['Content-Type' => 'text/csv']);
        }

        $headers = ['N° Pedido', 'Fecha', 'Cliente', 'Teléfono', 'Dirección', 'Total', 'Estado'];
        $rows = $pedidos->map(function ($p) {
            return [
                $p->numero_pedido,
                $p->created_at->format('Y-m-d H:i'),
                $p->nombre_cliente,
                $p->telefono_cliente ?? '',
                $p->direccion_cliente ?? '',
                $p->total,
                $p->estado,
            ];
        });

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }
            fclose($handle);
        }, $filename.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function actualizarEstado(Request $request, Pedido $pedido): RedirectResponse
    {
        $this->authorize('update', $pedido);

        $validated = $request->validate([
            'estado' => 'required|in:pendiente,confirmado,preparando,enviado,entregado,cancelado',
        ]);

        $estadoAnterior = $pedido->estado;
        $pedido->update(['estado' => $validated['estado']]);

        // Notificar al cliente sobre el cambio de estado
        $cliente = User::find($pedido->cliente_id);
        if ($cliente) {
            $mensajeAdicional = match ($validated['estado']) {
                'confirmado' => 'Tu pedido ha sido confirmado y está siendo preparado.',
                'preparando' => 'Tu pedido está en preparación. Te avisaremos cuando esté listo para envío.',
                'enviado' => 'Tu pedido ha sido enviado. Podrás rastrear tu entrega pronto.',
                'entregado' => 'Tu pedido ha sido entregado. ¡Gracias por tu compra!',
                'cancelado' => 'Tu pedido ha sido cancelado. Contacta al vendedor para más información.',
                default => null,
            };

            NotificationHelper::send($cliente, new ActualizacionEstadoPedidoNotification(
                $pedido,
                $estadoAnterior,
                $validated['estado'],
                $mensajeAdicional
            ));
        }

        return redirect()->back()->with('success', 'Estado del pedido actualizado correctamente.');
    }

    public function generarVenta(Pedido $pedido): RedirectResponse
    {
        $this->authorize('generarVenta', $pedido);

        if ($pedido->estado === 'cancelado') {
            return redirect()->back()->withErrors(['error' => 'No se puede generar venta de un pedido cancelado.']);
        }

        try {
            return DB::transaction(function () use ($pedido) {
                $venta = Venta::create([
                    'numero_factura' => 'V-'.$pedido->numero_pedido,
                    'cliente_id' => $pedido->cliente_id,
                    'fecha' => now(),
                    'subtotal' => $pedido->subtotal,
                    'iva' => $pedido->impuesto,
                    'total' => $pedido->total,
                    'estado' => 'pagada',
                    'notas' => 'Generado automáticamente desde Pedido #'.$pedido->numero_pedido,
                ]);

                foreach ($pedido->items as $item) {
                    DetalleVenta::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $item->producto_id,
                        'cantidad' => $item->cantidad,
                        'precio_unitario' => $item->precio_unitario,
                        'subtotal' => $item->subtotal,
                    ]);
                }

                // --- FINANZAS SYNC: Tesorería y Contabilidad ---

                // 1. Registro en Tesorería (Flujo de Caja)
                Tesoreria::create([
                    'tipo' => 'ingreso',
                    'monto' => $pedido->total,
                    'descripcion' => "Venta Marketplace - Pedido #{$pedido->numero_pedido}",
                    'fecha' => now(),
                    'referencia' => $venta->numero_factura,
                    'categoria' => 'Ventas Marketplace',
                    'estado' => 'completado',
                ]);

                // 2. Registro Contable (Asiento Diario)
                $asiento = Asiento::create([
                    'fecha' => now(),
                    'numero' => 'AS-VMT-'.time(),
                    'descripcion' => "Registro de venta marketplace #{$pedido->numero_pedido}",
                    'tipo' => 'venta',
                    'total_debe' => $pedido->total,
                    'total_haber' => $pedido->total,
                    'estado' => true,
                ]);

                $asiento->detalles()->create([
                    'cuenta' => 'Caja/Banco',
                    'cuenta_codigo' => '1.1.01',
                    'descripcion' => 'Ingreso por venta marketplace',
                    'debe' => $pedido->total,
                    'haber' => 0,
                ]);

                $asiento->detalles()->create([
                    'cuenta' => 'Ventas Marketplace',
                    'cuenta_codigo' => '4.1.01',
                    'descripcion' => 'Venta de productos',
                    'debe' => 0,
                    'haber' => $pedido->subtotal,
                ]);

                if ($pedido->impuesto > 0) {
                    $asiento->detalles()->create([
                        'cuenta' => 'IVA Débito Fiscal',
                        'cuenta_codigo' => '2.1.03',
                        'descripcion' => 'Impuesto sobre ventas',
                        'debe' => 0,
                        'haber' => $pedido->impuesto,
                    ]);
                }

                return redirect()->route('ventas.index');
            });
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Error al generar la venta: '.$e->getMessage()]);
        }
    }
}
