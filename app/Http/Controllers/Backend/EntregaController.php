<?php

namespace App\Http\Controllers\Backend;

use App\Exports\EntregasExport;
use App\Http\Controllers\Controller;
use App\Imports\EntregasImport;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Entrega;
use App\Models\GrupoTrabajo;
use App\Models\Pedido;
use App\Models\Vehiculo;
use App\Models\Venta;
use App\Scopes\OwnerScope;
use App\Services\DeliveryStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class EntregaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:flota.entregas.create', only: ['create', 'store']),
            new Middleware('permission:flota.entregas.edit', only: ['edit', 'update']),
            new Middleware('permission:flota.entregas.delete', only: ['destroy']),
            new Middleware('permission:flota.entregas.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:flota.entregas.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function __construct(protected DeliveryStatsService $statsService) {}

    public function index(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();

        $query = Entrega::where('owner_id', $ownerId)
            ->with(['items.producto', 'venta', 'conductor', 'vehiculo']);

        if ($request->filled('conductor_id')) {
            $query->where('conductor_id', $request->input('conductor_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $entregas = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $vehiculos = Vehiculo::where('owner_id', $ownerId)->orderBy('marca')->get();
        $conductores = Conductor::orderBy('nombre')->get(['id', 'nombre']);
        $clientes = Cliente::where('owner_id', $ownerId)->orderBy('nombre')->get();

        $ventas = Venta::where('owner_id', $ownerId)
            ->whereIn('estado', ['pagada', 'confirmado'])
            ->whereDoesntHave('entrega')
            ->with(['detalleVentas.producto', 'cliente'])
            ->orderBy('created_at', 'desc')
            ->get();

        $gruposTrabajo = GrupoTrabajo::where('owner_id', $ownerId)
            ->where('estado', 'activo')
            ->with(['conductores', 'miembros'])
            ->orderBy('nombre')
            ->get();

        $driverId = $request->input('conductor_id');
        $stats = $this->statsService->getDriverStats($ownerId, $driverId);

        return Inertia::render('Backend/Entregas/Index', [
            'entregas' => $entregas,
            'vehiculos' => $vehiculos,
            'conductores' => $conductores,
            'clientes' => $clientes,
            'ventas' => $ventas,
            'grupos_trabajo' => $gruposTrabajo,
            'stats' => $stats,
            'filters' => $request->only(['conductor_id', 'estado']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'venta_id' => 'nullable|integer|exists:ventas,id',
            'grupo_trabajo_id' => 'nullable|integer|exists:grupo_trabajos,id',
            'vehiculo_id' => 'nullable|integer|exists:vehiculos,id',
            'conductor_id' => 'nullable|integer|exists:conductores,id',
            'cliente' => 'nullable|string|max:255',
            'direccion' => 'nullable|string',
            'fecha_entrega' => 'nullable|date',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
            'descripcion' => 'nullable|string',
            'productos_json' => 'nullable|string',
        ]);

        $ownerId = Auth::user()->getOwnerId();
        $validated['owner_id'] = $ownerId;

        $entrega = Entrega::create($validated);

        $this->syncItemsFromVenta($entrega, $ownerId);

        return redirect()->route('entregas.index');
    }

    public function update(Request $request, Entrega $entrega): RedirectResponse
    {
        $validated = $request->validate([
            'venta_id' => 'nullable|integer',
            'grupo_trabajo_id' => 'nullable|integer|exists:grupo_trabajos,id',
            'vehiculo_id' => 'nullable|integer',
            'conductor_id' => 'nullable|integer',
            'cliente' => 'nullable|string|max:255',
            'direccion' => 'nullable|string',
            'fecha_entrega' => 'nullable|date',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
            'descripcion' => 'nullable|string',
            'productos_json' => 'nullable|string',
        ]);

        $estadoAnterior = $entrega->estado;
        $entrega->update($validated);

        // Re-sync items when venta_id changes or productos_json is provided
        if ($request->filled('venta_id')) {
            $this->syncItemsFromVenta($entrega, $entrega->owner_id);
        }

        if ($estadoAnterior !== $entrega->estado && $entrega->venta) {
            $venta = $entrega->venta;

            $map = [
                'pendiente' => 'pendiente',
                'en_ruta' => 'enviado',
                'entregado' => 'entregado',
                'cancelado' => 'cancelado',
            ];

            if (isset($map[$entrega->estado])) {
                $venta->update(['estado' => $map[$entrega->estado]]);

                if (str_starts_with($venta->numero, 'PEDIDO-')) {
                    $numeroPedido = substr($venta->numero, 7);
                    $pedido = Pedido::withoutGlobalScope(OwnerScope::class)
                        ->where('numero_pedido', $numeroPedido)
                        ->where('owner_id', $venta->owner_id)
                        ->first();

                    if ($pedido) {
                        $pedido->update(['estado' => $map[$entrega->estado]]);
                    }
                }
            }
        }

        return redirect()->route('entregas.index');
    }

    public function destroy(Entrega $entrega): RedirectResponse
    {
        $entrega->delete();

        return redirect()->route('entregas.index');
    }

    public function exportCsv(Request $request)
    {
        return Excel::download(new EntregasExport, 'entregas_'.now()->format('Ymd_His').'.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new EntregasExport, 'entregas_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        try {
            Excel::import(new EntregasImport, $request->file('archivo'));

            return redirect()->back()->with('success', 'Entregas importadas correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al importar: '.$e->getMessage());
        }
    }

    public function importExcel(Request $request): RedirectResponse
    {
        return $this->importCsv($request);
    }

    private function syncItemsFromVenta(Entrega $entrega, int $ownerId): void
    {
        if (! $entrega->venta_id) {
            return;
        }

        $venta = Venta::with('detalleVentas.producto')->find($entrega->venta_id);
        if (! $venta) {
            return;
        }

        // Delete existing items to re-sync
        $entrega->items()->delete();

        foreach ($venta->detalleVentas as $detalle) {
            if (! $detalle->producto) {
                continue;
            }

            $totals = $this->statsService->calculateItemTotals($detalle->producto_id, (float) $detalle->cantidad);

            $entrega->items()->create([
                'producto_id' => $detalle->producto_id,
                'cantidad_pedida' => $detalle->cantidad,
                'cantidad_entregada' => $detalle->cantidad,
                'unidad_medida' => $detalle->producto->unidad_medida ?? 'unidad',
                'subtotal_metrica' => ($totals['kg'] > 0) ? $totals['kg'] : $totals['litros'],
                'unidades_totales' => $totals['unidades'],
                'owner_id' => $ownerId,
            ]);
        }
    }
}
