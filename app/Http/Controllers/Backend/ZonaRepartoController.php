<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\DeliveryAsignacion;
use App\Models\DeliveryRate;
use App\Models\Repartidor;
use App\Models\Zone;
use App\Services\ZoneAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ZonaRepartoController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ZoneAnalytics $analytics) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:delivery.zonas-reparto.create', only: ['store']),
            new Middleware('permission:delivery.zonas-reparto.edit', only: ['update']),
            new Middleware('permission:delivery.zonas-reparto.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = (int) $request->user()->getOwnerId();
        $resumen = $this->analytics->resumen($ownerId);
        $rendimiento = $this->analytics->rendimiento($ownerId);

        $zonas = Zone::query()
            ->with('deliveryRates:id,zone_id,commune,cost,is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Zone $zona) => [
                'id' => $zona->id,
                'name' => $zona->name,
                'modo' => $zona->modo,
                'lat' => $zona->lat === null ? null : (float) $zona->lat,
                'lng' => $zona->lng === null ? null : (float) $zona->lng,
                'radio_km' => $zona->radio_km === null ? null : (float) $zona->radio_km,
                'capacidad_max' => (int) $zona->capacidad_max,
                'activa' => (bool) $zona->activa,
                'comunas' => $zona->deliveryRates
                    ->map(fn (DeliveryRate $rate) => [
                        'comuna' => $rate->commune,
                        'precio' => (float) $rate->cost,
                        'activa' => (bool) $rate->is_active,
                    ])
                    ->values()
                    ->all(),
                'uso' => collect($resumen['zonas'])->firstWhere('id', $zona->id) ?? null,
            ])
            ->values()
            ->all();

        $comunasDisponibles = DeliveryRate::query()
            ->where('calculation_type', 'comuna')
            ->whereNotNull('commune')
            ->pluck('commune')
            ->merge(Cliente::query()->whereNotNull('comuna')->pluck('comuna'))
            ->filter(fn (?string $comuna) => filled($comuna))
            ->unique(fn (string $comuna) => mb_strtolower(trim($comuna)))
            ->sort()
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
            'comunas_disponibles' => $comunasDisponibles,
            'repartidores' => $repartidores,
            'resumen' => $resumen,
            'rendimiento' => $rendimiento,
            'historial' => $historial,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);

        DB::transaction(function () use ($validated, $request): void {
            $comunas = $validated['comunas'];
            unset($validated['comunas']);

            $zona = Zone::create([
                ...$validated,
                'owner_id' => $request->user()->getOwnerId(),
            ]);

            $this->syncComunas($zona, $comunas);
        });

        $this->olvidarResumen($request);

        return back()->with('success', 'Zona creada correctamente.');
    }

    public function update(Request $request, Zone $zona): RedirectResponse
    {
        $validated = $this->validatedData($request);

        DB::transaction(function () use ($validated, $zona): void {
            $comunas = $validated['comunas'];
            unset($validated['comunas']);

            $zona->update($validated);
            $this->syncComunas($zona, $comunas);
        });

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

    /**
     * @return array{
     *   name: string,
     *   modo: string,
     *   lat: float|null,
     *   lng: float|null,
     *   radio_km: float|null,
     *   capacidad_max: int,
     *   activa: bool,
     *   comunas: list<array{comuna: string, precio: float}>
     * }
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'modo' => ['required', 'in:radio,comunas'],
            'lat' => ['required_if:modo,radio', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['required_if:modo,radio', 'nullable', 'numeric', 'between:-180,180'],
            'radio_km' => ['required_if:modo,radio', 'nullable', 'numeric', 'min:0.1', 'max:500'],
            'capacidad_max' => ['required', 'integer', 'min:1', 'max:999'],
            'activa' => ['sometimes', 'boolean'],
            'comunas' => ['required_if:modo,comunas', 'array', 'min:1'],
            'comunas.*.comuna' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'comunas.*.precio' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $data['activa'] = $request->boolean('activa');
        $data['comunas'] = $data['modo'] === 'comunas' ? $data['comunas'] : [];

        if ($data['modo'] === 'comunas') {
            $data['lat'] = null;
            $data['lng'] = null;
            $data['radio_km'] = null;
        }

        return $data;
    }

    /**
     * @param  list<array{comuna: string, precio: float}>  $comunas
     */
    private function syncComunas(Zone $zona, array $comunas): void
    {
        $tarifasExistentes = $zona->deliveryRates()
            ->get()
            ->keyBy(fn (DeliveryRate $rate) => mb_strtolower(trim($rate->commune)));
        $comunasSeleccionadas = collect($comunas)
            ->map(fn (array $comuna) => mb_strtolower(trim($comuna['comuna'])))
            ->all();

        $tarifasExistentes->each(function (DeliveryRate $rate, string $comuna) use ($comunasSeleccionadas): void {
            if (! in_array($comuna, $comunasSeleccionadas, true)) {
                $rate->update(['is_active' => false]);
            }
        });

        foreach ($comunas as $comuna) {
            $clave = mb_strtolower(trim($comuna['comuna']));
            $tarifa = $tarifasExistentes->get($clave);
            $datosTarifa = [
                'commune' => trim($comuna['comuna']),
                'calculation_type' => 'comuna',
                'cost' => $comuna['precio'],
                'is_active' => true,
            ];

            if ($tarifa) {
                $tarifa->update($datosTarifa);

                continue;
            }

            $zona->deliveryRates()->create([
                ...$datosTarifa,
                'owner_id' => $zona->owner_id,
            ]);
        }
    }
}
