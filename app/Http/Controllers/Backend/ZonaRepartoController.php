<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAsignacion;
use App\Models\Repartidor;
use App\Models\Zone;
use App\Services\ZoneAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ZonaRepartoController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ZoneAnalytics $analytics) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:zonas-reparto.create', only: ['store']),
            new Middleware('permission:zonas-reparto.edit', only: ['update']),
            new Middleware('permission:zonas-reparto.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = (int) $request->user()->getOwnerId();
        $resumen = $this->analytics->resumen($ownerId);
        $rendimiento = $this->analytics->rendimiento($ownerId);

        $zonas = Zone::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Zone $zona) => [
                'id' => $zona->id,
                'name' => $zona->name,
                'lat' => (float) $zona->lat,
                'lng' => (float) $zona->lng,
                'radio_km' => (float) $zona->radio_km,
                'capacidad_max' => (int) $zona->capacidad_max,
                'activa' => (bool) $zona->activa,
                'uso' => collect($resumen['zonas'])->firstWhere('id', $zona->id) ?? null,
            ])
            ->values()
            ->all();

        $repartidores = Repartidor::query()
            ->with('user:id,name')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get()
            ->map(fn (Repartidor $repartidor) => [
                'id' => $repartidor->id,
                'nombre' => $repartidor->user?->name,
                'estado' => $repartidor->estado,
                'lat' => (float) $repartidor->lat,
                'lng' => (float) $repartidor->lng,
                'radio_km' => (float) $repartidor->radio_km,
                'last_position_at' => $repartidor->last_position_at?->toISOString(),
            ])
            ->values()
            ->all();

        $historial = DeliveryAsignacion::query()
            ->with(['pedido:id,numero_pedido', 'zona:id,name', 'repartidor:id,name', 'asignador:id,name'])
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (DeliveryAsignacion $asignacion) => [
                'id' => $asignacion->id,
                'pedido' => $asignacion->pedido?->numero_pedido,
                'repartidor' => $asignacion->repartidor?->name,
                'zona' => $asignacion->zona?->name,
                'asignador' => $asignacion->asignador?->name,
                'motivo' => $asignacion->motivo,
                'fecha' => $asignacion->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Backend/ZonaReparto/Index', [
            'owner_id' => $ownerId,
            'zonas' => $zonas,
            'repartidores' => $repartidores,
            'resumen' => $resumen,
            'rendimiento' => $rendimiento,
            'historial' => $historial,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radio_km' => ['required', 'numeric', 'min:0.1', 'max:500'],
            'capacidad_max' => ['required', 'integer', 'min:1', 'max:999'],
            'activa' => ['sometimes', 'boolean'],
        ]);

        Zone::create([...$validated, 'activa' => $request->boolean('activa')]);

        $this->olvidarResumen($request);

        return back()->with('success', 'Zona creada correctamente.');
    }

    public function update(Request $request, Zone $zona): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radio_km' => ['required', 'numeric', 'min:0.1', 'max:500'],
            'capacidad_max' => ['required', 'integer', 'min:1', 'max:999'],
            'activa' => ['sometimes', 'boolean'],
        ]);

        $zona->update([...$validated, 'activa' => $request->boolean('activa')]);

        $this->olvidarResumen($request);

        return back()->with('success', 'Zona actualizada correctamente.');
    }

    public function destroy(Request $request, Zone $zona): RedirectResponse
    {
        $zona->delete();

        $this->olvidarResumen($request);

        return back()->with('success', 'Zona eliminada correctamente.');
    }

    private function olvidarResumen(Request $request): void
    {
        $this->analytics->olvidarResumen((int) $request->user()->getOwnerId());
    }
}
