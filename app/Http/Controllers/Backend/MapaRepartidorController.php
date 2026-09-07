<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DeliveryConfig;
use App\Models\Pedido;
use App\Models\Repartidor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapaRepartidorController extends Controller
{
    public function show(Request $request): Response
    {
        $repartidor = $request->attributes->get('delivery.repartidor');

        if (! $repartidor instanceof Repartidor) {
            abort(403, 'Perfil de repartidor no encontrado.');
        }

        $config = DeliveryConfig::query()->where('owner_id', $repartidor->owner_id)->first();
        $poolVisible = ! $config || $config->modo !== 'manual';

        $pool = [];

        if ($poolVisible && $repartidor->lat !== null && $repartidor->lng !== null) {
            $pool = Pedido::disponibleEnPool()
                ->whereNotNull('destino_lat')
                ->whereNotNull('destino_lng')
                ->orderBy('created_at')
                ->get()
                ->filter(function (Pedido $pedido) use ($repartidor) {
                    $distancia = $repartidor->distanciaA((float) $pedido->destino_lat, (float) $pedido->destino_lng);

                    return $distancia !== null && $distancia <= (float) $repartidor->radio_km;
                })
                ->values()
                ->map(fn (Pedido $pedido) => $this->pedidoResource($pedido, $repartidor))
                ->all();
        }

        $activos = Pedido::query()
            ->where('repartidor_id', $request->user()->id)
            ->whereIn('estado', ['preparando', 'enviado'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Pedido $pedido) => $this->pedidoResource($pedido, $repartidor))
            ->all();

        return Inertia::render('Delivery/Mapa', [
            'owner_id' => $repartidor->owner_id,
            'repartidor' => [
                'id' => $repartidor->id,
                'user_id' => $repartidor->user_id,
                'estado' => $repartidor->estado,
                'lat' => $repartidor->lat,
                'lng' => $repartidor->lng,
                'radio_km' => $repartidor->radio_km,
                'last_position_at' => $repartidor->last_position_at?->toISOString(),
            ],
            'config' => $config ? [
                'modo' => $config->modo,
                'pool_timeout_min' => $config->pool_timeout_min,
                'pool_reenvio_min' => $config->pool_reenvio_min,
                'pool_reenvios_max' => $config->pool_reenvios_max,
            ] : null,
            'pool' => $pool,
            'activos' => $activos,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pedidoResource(Pedido $pedido, ?Repartidor $repartidor = null): array
    {
        $distancia = null;

        if ($repartidor && $pedido->destino_lat !== null && $pedido->destino_lng !== null) {
            $distancia = $repartidor->distanciaA((float) $pedido->destino_lat, (float) $pedido->destino_lng);
        }

        return [
            'id' => $pedido->id,
            'numero_pedido' => $pedido->numero_pedido,
            'estado' => $pedido->estado,
            'repartidor_id' => $pedido->repartidor_id,
            'total' => $pedido->total,
            'metodo_pago' => $pedido->metodo_pago,
            'nombre_cliente' => $pedido->nombre_cliente,
            'telefono_cliente' => $pedido->telefono_cliente,
            'direccion_cliente' => $pedido->direccion_cliente,
            'destino_lat' => $pedido->destino_lat,
            'destino_lng' => $pedido->destino_lng,
            'distancia_km' => $distancia !== null ? round($distancia, 2) : null,
            'notas' => $pedido->notas,
            'hora_aceptado' => $pedido->hora_aceptado?->toISOString(),
            'hora_recogido' => $pedido->hora_recogido?->toISOString(),
            'hora_entregado' => $pedido->hora_entregado?->toISOString(),
            'created_at' => $pedido->created_at?->toISOString(),
        ];
    }
}
