<?php

namespace App\Http\Controllers\Api\Driver;

use App\Events\DeliveryOrderPoolUpdated;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAsignacion;
use App\Models\DriverOrderOffer;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\DeliveryPedidoAceptadoNotification;
use App\Scopes\OwnerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la Bandeja Global / Marketplace de Repartos Abiertos.
 *
 * Permite a cualquier repartidor independiente (freelancer tipo Uber/Rappi)
 * consultar los pedidos disponibles en la red abierta y "tomar" (claim)
 * un pedido de forma atómica y segura contra condiciones de carrera.
 */
class DriverMarketplaceController extends Controller
{
    /**
     * GET /api/v1/driver/marketplace/orders
     *
     * Bandeja global de pedidos abiertos (`dispatch_mode = 'marketplace'`).
     * Filtra automáticamente por proximidad y radio de cobertura del repartidor.
     */
    public function orders(Request $request): JsonResponse
    {
        $repartidor = $this->getRepartidor($request);

        if (! $repartidor) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un perfil de repartidor activo.',
            ], 404);
        }

        if (! $repartidor->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Tu perfil de repartidor aún no está aprobado para operar.',
            ], 403);
        }

        if (! $repartidor->is_online || ! $repartidor->isDisponible()) {
            return response()->json([
                'success' => true,
                'message' => 'Debes estar conectado (Online) y disponible para ver la bandeja de pedidos abiertos.',
                'is_online' => false,
                'orders' => [],
            ]);
        }

        $query = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->disponibleEnPool()
            ->marketplace()
            ->with(['items.producto'])
            ->whereNotNull('destino_lat')
            ->whereNotNull('destino_lng')
            ->orderBy('created_at', 'desc');

        $pedidos = $query->get();

        // Filtrar por radio de distancia si el repartidor tiene coordenadas GPS
        if ($repartidor->lat !== null && $repartidor->lng !== null) {
            $pedidos = $pedidos->filter(function (Pedido $pedido) use ($repartidor) {
                $distancia = $repartidor->distanciaA((float) $pedido->destino_lat, (float) $pedido->destino_lng);

                return $distancia !== null && $distancia <= (float) $repartidor->radio_km;
            })->values();
        }

        $ordersResource = $pedidos->map(fn (Pedido $pedido) => $this->pedidoResource($pedido, $repartidor))->all();

        return response()->json([
            'success' => true,
            'count' => count($ordersResource),
            'is_online' => true,
            'orders' => $ordersResource,
        ]);
    }

    /**
     * POST /api/v1/driver/marketplace/orders/{pedido}/claim
     *
     * Flujo atómico para "tomar" un pedido abierto del marketplace.
     * Bloquea el registro con transacción `lockForUpdate` para garantizar
     * que un único repartidor se lo asigne, evitando dobles tomas.
     */
    public function claim(Request $request, Pedido $pedido): JsonResponse
    {
        $repartidor = $this->getRepartidor($request);

        if (! $repartidor) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un perfil de repartidor activo.',
            ], 404);
        }

        if (! $repartidor->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Tu perfil de repartidor no está aprobado para tomar pedidos.',
            ], 403);
        }

        if (! $repartidor->is_online || ! $repartidor->isDisponible()) {
            return response()->json([
                'success' => false,
                'message' => 'Debes estar conectado (Online) y disponible para tomar pedidos.',
            ], 409);
        }

        $resultado = DB::transaction(function () use ($pedido, $request, $repartidor) {
            $bloqueado = Pedido::query()
                ->withoutGlobalScope(OwnerScope::class)
                ->lockForUpdate()
                ->find($pedido->getKey());

            if (! $bloqueado
                || ! in_array($bloqueado->estado, ['preparando', 'pendiente'], true)
                || $bloqueado->repartidor_id !== null
                || $bloqueado->hora_aceptado !== null
                || $bloqueado->pool_bloqueado
                || ($bloqueado->pool_visible_at !== null && $bloqueado->pool_visible_at->isFuture())
            ) {
                return 'no_disponible';
            }

            // Validar que el pedido realmente sea de red abierta (marketplace)
            if (! $bloqueado->isMarketplace() && (int) $bloqueado->owner_id !== (int) $repartidor->owner_id) {
                return 'no_autorizado';
            }

            // Validar capacidad activa del repartidor
            $capacidadLlena = $this->repartidorCapacidadLlena((int) $request->user()->id, (int) $repartidor->capacidad_max);
            if ($capacidadLlena) {
                return 'capacidad_llena';
            }

            // Asignar el pedido al repartidor y congelar hora de aceptación
            $bloqueado->update([
                'repartidor_id' => $request->user()->id,
                'hora_aceptado' => now(),
            ]);

            // Cancelar ofertas pendientes que pudieran existir para otros repartidores sobre este pedido
            DriverOrderOffer::where('pedido_id', $bloqueado->id)
                ->where('status', DriverOrderOffer::STATUS_PENDING)
                ->update([
                    'status' => DriverOrderOffer::STATUS_CANCELLED,
                    'responded_at' => now(),
                ]);

            return 'ok';
        });

        if ($resultado === 'no_autorizado') {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido está reservado para la flota interna de la empresa creadora.',
            ], 403);
        }

        if ($resultado === 'capacidad_llena') {
            return response()->json([
                'success' => false,
                'message' => 'Has alcanzado tu capacidad máxima de repartos activos simultáneos.',
            ], 409);
        }

        if ($resultado !== 'ok') {
            return response()->json([
                'success' => false,
                'message' => 'El pedido ya fue tomado por otro repartidor o no está disponible.',
            ], 409);
        }

        $pedido->refresh();
        $pedido->load(['conversacion', 'items.producto']);

        // Registrar en historial de asignaciones
        DeliveryAsignacion::registrar(
            $pedido,
            (int) $request->user()->id,
            $this->zonaParaDestino($pedido),
            (int) $request->user()->id,
            'marketplace_claim'
        );

        // Notificar en tiempo real vía WebSocket / Reverb
        broadcast(new DeliveryOrderPoolUpdated($pedido, 'aceptado'));

        // Notificar al vendedor de la empresa creadora
        $vendedorId = $pedido->conversacion?->vendedor_id ?? $pedido->user_id;
        $vendedor = $vendedorId ? User::find($vendedorId) : null;

        if ($vendedor) {
            $repartidor->load('user');
            NotificationHelper::send($vendedor, new DeliveryPedidoAceptadoNotification($pedido, $repartidor));
        }

        return response()->json([
            'success' => true,
            'message' => '¡Has tomado el pedido exitosamente! Puedes proceder al retiro.',
            'pedido' => $this->pedidoResource($pedido, $repartidor),
        ]);
    }

    private function getRepartidor(Request $request): ?Repartidor
    {
        $cached = $request->attributes->get('delivery.repartidor');
        if ($cached instanceof Repartidor) {
            return $cached;
        }

        return Repartidor::withoutGlobalScope(OwnerScope::class)
            ->where('user_id', $request->user()->id)
            ->first();
    }

    private function repartidorCapacidadLlena(int $repartidorUserId, int $capacidadMax): bool
    {
        $activos = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('repartidor_id', $repartidorUserId)
            ->whereIn('estado', ['preparando', 'enviado'])
            ->count();

        return $activos >= $capacidadMax;
    }

    private function zonaParaDestino(Pedido $pedido): ?Zone
    {
        if ($pedido->destino_lat === null || $pedido->destino_lng === null) {
            return null;
        }

        return Zone::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $pedido->owner_id)
            ->activa()
            ->where('modo', 'radio')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get()
            ->filter(fn (Zone $zona) => $zona->contiene((float) $pedido->destino_lat, (float) $pedido->destino_lng))
            ->sortBy(fn (Zone $zona) => $zona->distanciaA((float) $pedido->destino_lat, (float) $pedido->destino_lng))
            ->first();
    }

    private function pedidoResource(Pedido $pedido, Repartidor $repartidor): array
    {
        $distancia = null;

        if ($pedido->destino_lat !== null && $pedido->destino_lng !== null && $repartidor->lat !== null && $repartidor->lng !== null) {
            $distancia = $repartidor->distanciaA((float) $pedido->destino_lat, (float) $pedido->destino_lng);
        }

        $costoEnvio = $pedido->costoEnvioEfectivo();
        $gananciaEstimada = $repartidor->gananciaEstimada($costoEnvio);

        $items = $pedido->items->map(function ($item) {
            return [
                'id' => $item->id,
                'producto_id' => $item->producto_id,
                'nombre_producto' => $item->nombre_producto,
                'precio_unitario' => (float) $item->precio_unitario,
                'cantidad' => (float) $item->cantidad,
                'subtotal' => (float) $item->subtotal,
                'envase_retornable' => (bool) ($item->producto?->envase_retornable ?? false),
            ];
        })->all();

        return [
            'id' => $pedido->id,
            'numero_pedido' => $pedido->numero_pedido,
            'estado' => $pedido->estado,
            'dispatch_mode' => $pedido->dispatch_mode ?? 'marketplace',
            'delivery_type' => $pedido->dispatch_mode ?? 'marketplace',
            'repartidor_id' => $pedido->repartidor_id,
            'subtotal' => (float) ($pedido->subtotal ?? 0),
            'impuesto' => (float) ($pedido->impuesto ?? 0),
            'total' => (float) $pedido->total,
            'costo_envio' => $costoEnvio,
            'ganancia_estimada' => $gananciaEstimada,
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
            'items' => $items,
        ];
    }
}
