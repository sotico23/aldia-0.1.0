<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\AsignarPoolRequest;
use App\Http\Requests\Delivery\StoreZoneRequest;
use App\Http\Requests\Delivery\UpdateZoneRequest;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\Zone;
use App\Services\ZoneAnalytics;
use App\Services\ZoneAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function __construct(private readonly ZoneAnalytics $analytics) {}

    /**
     * GET /api/v1/zones
     * Lista las zonas del tenant con su estado de uso.
     */
    public function index(Request $request): JsonResponse
    {
        $pedidos = $this->analytics->pedidosEnCircuito();

        $zonas = Zone::query()->orderBy('name')->get()
            ->map(fn (Zone $zona) => [
                'id' => $zona->id,
                'name' => $zona->name,
                'lat' => (float) $zona->lat,
                'lng' => (float) $zona->lng,
                'radio_km' => (float) $zona->radio_km,
                'capacidad_max' => (int) $zona->capacidad_max,
                'activa' => (bool) $zona->activa,
                'uso' => $this->analytics->resumenZona($zona, $pedidos),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'zonas' => $zonas,
        ]);
    }

    /**
     * POST /api/v1/zones
     * Crea una zona de reparto.
     */
    public function store(StoreZoneRequest $request): JsonResponse
    {
        $zona = Zone::create($request->validated());

        $this->analytics->olvidarResumen($request->user()->getOwnerId());

        return response()->json([
            'success' => true,
            'message' => 'Zona creada.',
            'zona' => $zona,
        ], 201);
    }

    /**
     * PUT /api/v1/zones/{zone}
     * Actualiza una zona.
     */
    public function update(UpdateZoneRequest $request, Zone $zone): JsonResponse
    {
        $zone->update($request->validated());

        $this->analytics->olvidarResumen($request->user()->getOwnerId());

        return response()->json([
            'success' => true,
            'message' => 'Zona actualizada.',
            'zona' => $zone,
        ]);
    }

    /**
     * DELETE /api/v1/zones/{zone}
     * Elimina una zona.
     */
    public function destroy(Request $request, Zone $zone): JsonResponse
    {
        $zone->delete();

        $this->analytics->olvidarResumen($request->user()->getOwnerId());

        return response()->json([
            'success' => true,
            'message' => 'Zona eliminada.',
        ]);
    }

    /**
     * GET /api/v1/zones/geojson
     * Mapa administrativo: zonas (Point + estadisticas) y repartidores
     * con posicion en vivo como FeatureCollection (RFC 7946).
     */
    public function geojson(): JsonResponse
    {
        $pedidos = $this->analytics->pedidosEnCircuito();

        $zonas = Zone::query()->orderBy('name')->get()
            ->map(fn (Zone $zona) => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $zona->lng, (float) $zona->lat],
                ],
                'properties' => [
                    'tipo' => 'zona',
                    ...$this->analytics->resumenZona($zona, $pedidos),
                ],
            ])
            ->values()
            ->all();

        $repartidores = Repartidor::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get();

        $conteos = Pedido::query()
            ->whereIn('estado', ['preparando', 'enviado'])
            ->whereNotNull('repartidor_id')
            ->get('repartidor_id')
            ->groupBy('repartidor_id')
            ->map->count();

        $featuresRepartidores = $repartidores
            ->map(fn (Repartidor $repartidor) => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $repartidor->lng, (float) $repartidor->lat],
                ],
                'properties' => [
                    'tipo' => 'repartidor',
                    'id' => $repartidor->id,
                    'user_id' => $repartidor->user_id,
                    'estado' => $repartidor->estado,
                    'radio_km' => (float) $repartidor->radio_km,
                    'last_position_at' => $repartidor->last_position_at?->toISOString(),
                    'pedidos_activos' => $conteos->get($repartidor->user_id, 0),
                ],
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'type' => 'FeatureCollection',
            'features' => [...$zonas, ...$featuresRepartidores],
        ]);
    }

    /**
     * POST /api/v1/zones/pool/{pedido}/asignar
     * Asigna manualmente un pedido del pool a un repartidor o a la mejor
     * opcion dentro de una zona (accion de administracion por tenant).
     */
    public function asignarPool(AsignarPoolRequest $request, Pedido $pedido): JsonResponse
    {
        $repartidor = null;
        $zona = null;

        if ($request->filled('repartidor_id')) {
            $repartidor = Repartidor::where('user_id', $request->integer('repartidor_id'))->first();

            if (! $repartidor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repartidor no encontrado en tu empresa.',
                ], 404);
            }
        }

        if ($request->filled('zona_id')) {
            $zona = Zone::find($request->integer('zona_id'));

            if (! $zona) {
                return response()->json([
                    'success' => false,
                    'message' => 'Zona no encontrada.',
                ], 404);
            }
        }

        $resultado = app(ZoneAssignment::class)->asignarUnPedido(
            $pedido,
            $repartidor,
            $zona,
            $request->user()->id
        );

        if (! $resultado['success']) {
            $mensajes = [
                'no_en_pool' => 'El pedido no está disponible en el pool.',
                'sin_coordenadas' => 'El pedido no tiene coordenadas de destino registradas.',
                'fuera_de_zona' => 'El destino del pedido está fuera de la zona seleccionada.',
                'zona_llena' => 'La zona ha alcanzado su capacidad máxima.',
                'sin_repartidor' => 'No hay repartidores disponibles para asignar.',
                'repartidor_ocupado' => 'El repartidor ya tiene un reparto activo.',
            ];

            return response()->json([
                'success' => false,
                'message' => $mensajes[$resultado['motivo']] ?? 'No se pudo asignar el pedido.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pedido asignado manualmente.',
            'motivo' => $resultado['motivo'],
            'repartidor_id' => $resultado['repartidor_id'],
            'pedido' => [
                'id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'estado' => $pedido->estado,
                'repartidor_id' => $pedido->repartidor_id,
                'hora_aceptado' => $pedido->hora_aceptado?->toISOString(),
            ],
        ]);
    }

    /**
     * GET /api/v1/zones/resumen
     * Métricas agregadas por zona: capacidad, uso y repartidores.
     */
    public function resumen(Request $request): JsonResponse
    {
        $resumen = $this->analytics->resumen($request->user()->getOwnerId());

        return response()->json([
            'success' => true,
            ...$resumen,
        ]);
    }
}
