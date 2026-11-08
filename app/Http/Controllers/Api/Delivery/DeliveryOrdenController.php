<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Events\DeliveryOrderPoolUpdated;
use App\Events\DeliveryPositionUpdated;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\RejectOrderRequest;
use App\Http\Requests\Delivery\UpdateAvailabilityRequest;
use App\Http\Requests\Delivery\UpdateLocationRequest;
use App\Models\DeliveryAsignacion;
use App\Models\DeliveryConfig;
use App\Models\DeliveryPosition;
use App\Models\DriverOrderOffer;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use App\Models\Vacio;
use App\Models\Zone;
use App\Notifications\ActualizacionEstadoPedidoNotification;
use App\Notifications\DeliveryPedidoAceptadoNotification;
use App\Scopes\OwnerScope;
use App\Services\PedidoPoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryOrdenController extends Controller
{
    /**
     * POST /api/v1/delivery/location
     * Heartbeat GPS: actualiza posicion, guarda historico y broadcast.
     */
    public function location(UpdateLocationRequest $request): JsonResponse
    {
        $repartidor = $this->repartidor($request);
        $lat = (float) $request->validated('lat');
        $lng = (float) $request->validated('lng');

        $repartidor->update([
            'lat' => $lat,
            'lng' => $lng,
            'last_position_at' => now(),
        ]);

        $position = DeliveryPosition::create([
            'owner_id' => $repartidor->owner_id,
            'repartidor_id' => $repartidor->id,
            'lat' => $lat,
            'lng' => $lng,
        ]);

        broadcast(new DeliveryPositionUpdated($position));

        return response()->json([
            'success' => true,
            'estado' => $repartidor->estado,
            'last_position_at' => $repartidor->last_position_at?->toISOString(),
        ]);
    }

    /**
     * GET /api/v1/delivery/me
     * Estado del repartidor, config del pool y pedido activo.
     */
    public function me(Request $request): JsonResponse
    {
        $repartidor = $this->repartidor($request);

        $config = DeliveryConfig::where('owner_id', $repartidor->owner_id)->first();

        $pedidoActivo = Pedido::with(['conversacion', 'items.producto'])
            ->where('repartidor_id', $request->user()->id)
            ->whereIn('estado', ['preparando', 'enviado'])
            ->latest('created_at')
            ->first();

        return response()->json([
            'success' => true,
            'repartidor' => [
                'id'              => $repartidor->id,
                'owner_id'        => (int) $repartidor->owner_id,
                'estado'          => $repartidor->estado,
                'driver_type'     => $repartidor->driver_type,
                'approval_status' => $repartidor->approval_status,
                'rejected_reason' => $repartidor->rejected_reason,
                'is_online'       => $repartidor->is_online,
                'comision_pct'    => (float) $repartidor->comision_pct,
                'capacidad_max'   => (int) $repartidor->capacidad_max,
                'lat'             => $repartidor->lat,
                'lng'             => $repartidor->lng,
                'radio_km'        => $repartidor->radio_km,
                'vehiculo_id'     => $repartidor->vehiculo_id,
                'last_position_at' => $repartidor->last_position_at?->toISOString(),
                'last_online_at'  => $repartidor->last_online_at?->toISOString(),
            ],
            'config' => $config ? [
                'modo'             => $config->modo,
                'pool_timeout_min' => $config->pool_timeout_min,
                'pool_reenvio_min' => $config->pool_reenvio_min,
                'pool_reenvios_max' => $config->pool_reenvios_max,
            ] : null,
            'pedido_activo' => $pedidoActivo ? $this->pedidoResource($pedidoActivo) : null,
        ]);
    }

    /**
     * POST /api/v1/delivery/availability
     * Toggle disponible/ocupado; libera el pedido activo sin recoger si aplica.
     * Para el modelo Uber se recomienda usar /api/v1/driver/toggle-online en su lugar.
     */
    public function availability(UpdateAvailabilityRequest $request): JsonResponse
    {
        $repartidor = $this->repartidor($request);
        $estado = $request->validated('estado');
        $pedidoLiberado = null;

        if ($estado !== 'disponible') {
            $pedidoActivo = Pedido::where('repartidor_id', $request->user()->id)
                ->where('estado', 'preparando')
                ->latest('created_at')
                ->first();

            if ($pedidoActivo) {
                app(PedidoPoolService::class)->devolverAlPool(
                    $pedidoActivo,
                    'liberado',
                    $request->user()->id,
                    contarReenvio: false
                );
                $pedidoLiberado = $pedidoActivo->id;
            }
        }

        // Sincronizar is_online con el nuevo estado
        $isOnline = ($estado === 'disponible');
        $repartidor->update([
            'estado'    => $estado,
            'is_online' => $isOnline,
        ]);

        return response()->json([
            'success'         => true,
            'estado'          => $estado,
            'is_online'       => $isOnline,
            'pedido_liberado' => $pedidoLiberado,
        ]);
    }

    /**
     * GET /api/v1/delivery/orders
     * Pool de pedidos dentro del radio + pedidos activos del repartidor.
     * Solo muestra pool si el repartidor está online y disponible.
     */
    public function orders(Request $request): JsonResponse
    {
        $repartidor = $this->repartidor($request);

        $config = DeliveryConfig::where('owner_id', $repartidor->owner_id)->first();
        $poolVisible = ! $config || $config->modo !== 'manual';

        $pool = [];

        // Solo mostrar pool si está online, disponible y aprobado
        if ($poolVisible
            && $repartidor->is_online
            && $repartidor->isDisponible()
            && $repartidor->isApproved()
            && $repartidor->lat !== null
            && $repartidor->lng !== null
        ) {
            $poolQuery = Pedido::query()
                ->withoutGlobalScope(OwnerScope::class)
                ->disponibleEnPool()
                ->with(['items.producto'])
                ->whereNotNull('destino_lat')
                ->whereNotNull('destino_lng');

            if ($repartidor->isFreelancer()) {
                // Freelancer: ve pedidos del marketplace global + pedidos de su propia empresa
                $poolQuery->where(function ($q) use ($repartidor) {
                    $q->where('dispatch_mode', Pedido::DISPATCH_MARKETPLACE)
                        ->orWhere(function ($sub) use ($repartidor) {
                            $sub->where('owner_id', $repartidor->owner_id)
                                ->where(fn ($w) => $w->whereNull('dispatch_mode')->orWhere('dispatch_mode', Pedido::DISPATCH_INTERNAL));
                        });
                });
            } else {
                // Empleado: solo pedidos de su empresa
                $poolQuery->where('owner_id', $repartidor->owner_id);
            }

            $pool = $poolQuery->orderBy('created_at')
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
            ->withoutGlobalScope(OwnerScope::class)
            ->with(['conversacion', 'items.producto'])
            ->where('repartidor_id', $request->user()->id)
            ->whereIn('estado', ['preparando', 'enviado'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Pedido $pedido) => $this->pedidoResource($pedido, $repartidor))
            ->all();

        return response()->json([
            'success'         => true,
            'pool'            => $pool,
            'pedidos_activos' => $activos,
            'is_online'       => $repartidor->is_online,
        ]);
    }

    /**
     * POST /api/v1/delivery/orders/{pedido}/accept
     * Transaccion + lockForUpdate para evitar doble aceptacion.
     */
    public function accept(Request $request, Pedido $pedido): JsonResponse
    {
        $repartidor = $this->repartidor($request);

        if (! $repartidor->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Tu perfil de repartidor aún no está aprobado para operar.',
            ], 403);
        }

        if (! $repartidor->is_online || ! $repartidor->isDisponible()) {
            return response()->json([
                'success' => false,
                'message' => 'Debes estar conectado (Online) y disponible para aceptar repartos.',
            ], 409);
        }

        $resultado = \DB::transaction(function () use ($pedido, $request, $repartidor) {
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

            // Si el pedido es interno, solo repartidores vinculados a la empresa pueden tomarlo
            if ($bloqueado->isInternal() && (int) $bloqueado->owner_id !== (int) $repartidor->owner_id) {
                return 'no_autorizado_internal';
            }

            $repartidorLleno = $this->repartidorCapacidadLlena((int) $request->user()->id, (int) $bloqueado->owner_id);

            if ($repartidorLleno) {
                return 'capacidad_llena';
            }

            $bloqueado->update([
                'repartidor_id' => $request->user()->id,
                'hora_aceptado' => now(),
            ]);

            // Si existían ofertas para este pedido en el pool, resolverlas
            DriverOrderOffer::where('pedido_id', $bloqueado->id)
                ->where('status', DriverOrderOffer::STATUS_PENDING)
                ->update([
                    'status' => DriverOrderOffer::STATUS_CANCELLED,
                    'responded_at' => now(),
                ]);

            return 'ok';
        });

        if ($resultado === 'no_autorizado_internal') {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido está reservado para la flota interna de la empresa.',
            ], 403);
        }

        if ($resultado === 'capacidad_llena') {
            return response()->json([
                'success' => false,
                'message' => 'Has alcanzado tu capacidad máxima de repartos activos.',
            ], 409);
        }

        if ($resultado !== 'ok') {
            return response()->json([
                'success' => false,
                'message' => 'El pedido ya no está disponible en el pool.',
            ], 409);
        }

        $pedido->refresh();
        $pedido->load(['conversacion', 'items.producto']);

        DeliveryAsignacion::registrar(
            $pedido,
            (int) $request->user()->id,
            $this->zonaParaDestino($pedido),
            (int) $request->user()->id,
            'aceptado_pool'
        );

        broadcast(new DeliveryOrderPoolUpdated($pedido, 'aceptado'));

        $vendedorId = $pedido->conversacion?->vendedor_id ?? $pedido->user_id;
        $vendedor = $vendedorId ? User::find($vendedorId) : null;

        if ($vendedor) {
            $repartidor->load('user');
            NotificationHelper::send($vendedor, new DeliveryPedidoAceptadoNotification($pedido, $repartidor));
        }

        return response()->json([
            'success' => true,
            'message' => 'Reparto aceptado.',
            'pedido' => $this->pedidoResource($pedido, $repartidor),
        ]);
    }

    /**
     * POST /api/v1/delivery/orders/{pedido}/pickup
     * Solo el repartidor asignado; estado enviado + hora_recogido.
     */
    public function pickup(Request $request, Pedido $pedido): JsonResponse
    {
        if ((int) $pedido->repartidor_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Este reparto no está asignado a ti.',
            ], 403);
        }

        if ($pedido->estado !== 'preparando') {
            return response()->json([
                'success' => false,
                'message' => 'El pedido no está listo para recoger.',
            ], 409);
        }

        $pedido->update([
            'estado' => 'enviado',
            'hora_recogido' => now(),
        ]);

        $pedido->load(['conversacion', 'items.producto']);
        broadcast(new DeliveryOrderPoolUpdated($pedido, 'recogido'));

        $cliente = $pedido->cliente_id ? User::find($pedido->cliente_id) : null;

        if ($cliente) {
            NotificationHelper::send($cliente, new ActualizacionEstadoPedidoNotification(
                $pedido,
                'preparando',
                'enviado',
                'Tu pedido ha sido enviado. Podrás rastrear tu entrega pronto.'
            ));
        }

        return response()->json([
            'success' => true,
            'message' => 'Pedido recogido.',
            'pedido' => $this->pedidoResource($pedido, $this->repartidor($request)),
        ]);
    }

    /**
     * POST /api/v1/delivery/orders/{pedido}/delivered
     * Solo el repartidor asignado; entrega + libera disponibilidad.
     */
    public function delivered(Request $request, Pedido $pedido): JsonResponse
    {
        if ((int) $pedido->repartidor_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Este reparto no está asignado a ti.',
            ], 403);
        }

        if ($pedido->estado !== 'enviado') {
            return response()->json([
                'success' => false,
                'message' => 'El pedido debe estar en estado enviado para marcarlo como entregado.',
            ], 409);
        }

        $pedido->update([
            'estado' => 'entregado',
            'fecha_entrega' => now(),
            'hora_entregado' => now(),
        ]);

        $repartidor = $this->repartidor($request);

        // Congelar costo de envío y ganancia para el histórico de ganancias.
        $costoEnvio = $pedido->costoEnvioEfectivo();
        $distanciaKm = $pedido->distancia_km;

        if (($distanciaKm === null || (float) $distanciaKm <= 0)
            && $repartidor->lat !== null
            && $repartidor->lng !== null
            && $pedido->destino_lat !== null
            && $pedido->destino_lng !== null
        ) {
            $distancia = $repartidor->distanciaA(
                (float) $pedido->destino_lat,
                (float) $pedido->destino_lng
            );
            $distanciaKm = $distancia !== null ? round($distancia, 2) : null;
        }

        $pedido->update([
            'costo_envio' => $costoEnvio,
            'ganancia_repartidor' => $repartidor->gananciaEstimada($costoEnvio),
            'distancia_km' => $distanciaKm,
        ]);

        $repartidor->update(['estado' => 'disponible']);

        // Registrar envases vacíos de gas retornados por el cliente si aplica
        $cantidadRetornada = (int) $request->input('cantidad_retornada', 0);
        if ($cantidadRetornada > 0) {
            Vacio::create([
                'owner_id' => $pedido->owner_id,
                'cliente_id' => $pedido->cliente_id,
                'cantidad' => $cantidadRetornada,
                'estado' => 'bueno',
                'ubicacion' => 'Vehiculo Repartidor ' . $repartidor->id,
                'observaciones' => "Envases retornados en entrega de Pedido #{$pedido->numero_pedido}",
            ]);
        }

        $pedido->load(['conversacion', 'items.producto']);
        broadcast(new DeliveryOrderPoolUpdated($pedido, 'entregado'));

        $cliente = $pedido->cliente_id ? User::find($pedido->cliente_id) : null;

        if ($cliente) {
            NotificationHelper::send($cliente, new ActualizacionEstadoPedidoNotification(
                $pedido,
                'enviado',
                'entregado',
                'Tu pedido ha sido entregado. ¡Gracias por tu compra!'
            ));
        }

        return response()->json([
            'success' => true,
            'message' => 'Pedido entregado.',
            'pedido' => $this->pedidoResource($pedido, $repartidor),
        ]);
    }

    /**
     * POST /api/v1/delivery/orders/{pedido}/reject
     * Devuelve el pedido al pool con log y motivo.
     */
    public function reject(RejectOrderRequest $request, Pedido $pedido): JsonResponse
    {
        if ($pedido->repartidor_id !== null && (int) $pedido->repartidor_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Este reparto no está asignado a ti.',
            ], 403);
        }

        if ($pedido->estado !== 'preparando') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden rechazar pedidos en preparación.',
            ], 409);
        }

        $resultado = app(PedidoPoolService::class)->devolverAlPool(
            $pedido,
            'rechazado',
            $request->user()->id,
            motivoLog: $request->validated('motivo'),
        );

        return response()->json([
            'success' => true,
            'message' => $resultado === 'bloqueado'
                ? 'Pedido retirado del pool por límite de reenvíos.'
                : 'Pedido devuelto al pool.',
            'resultado' => $resultado,
            'pedido' => $this->pedidoResource(
                $pedido->fresh()->load(['conversacion', 'items.producto']),
                $this->repartidor($request)
            ),
        ]);
    }

    /**
     * POST /api/v1/delivery/orders/{pedido}/cancel
     * Cancela el pedido con registro de motivo.
     */
    public function cancel(Request $request, Pedido $pedido): JsonResponse
    {
        if ($pedido->repartidor_id !== null && (int) $pedido->repartidor_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Este reparto no está asignado a ti.',
            ], 403);
        }

        $motivo = $request->input('motivo', 'Cancelado desde app móvil');

        $pedido->update([
            'estado' => 'cancelado',
            'notas' => trim(($pedido->notas ?? '') . " | Cancelado: $motivo"),
        ]);

        $repartidor = $this->repartidor($request);
        $repartidor->update(['estado' => 'disponible']);

        broadcast(new DeliveryOrderPoolUpdated($pedido, 'cancelado'));

        return response()->json([
            'success' => true,
            'message' => 'Pedido cancelado.',
            'pedido' => $this->pedidoResource(
                $pedido->fresh()->load(['conversacion', 'items.producto']),
                $repartidor
            ),
        ]);
    }

    private function repartidor(Request $request): Repartidor
    {
        $repartidor = $request->attributes->get('delivery.repartidor');

        if ($repartidor instanceof Repartidor) {
            return $repartidor;
        }

        return Repartidor::where('user_id', $request->user()->id)->firstOrFail();
    }

    /**
     * True si el repartidor ya alcanzo su capacidad maxima de pedidos activos.
     */
    private function repartidorCapacidadLlena(int $repartidorUserId, ?int $ownerId = null): bool
    {
        $repartidor = Repartidor::withoutGlobalScope(OwnerScope::class)
            ->where('user_id', $repartidorUserId)
            ->first();

        if (! $repartidor) {
            return true;
        }

        $activos = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('repartidor_id', $repartidorUserId)
            ->whereIn('estado', ['preparando', 'enviado'])
            ->count();

        return $activos >= (int) $repartidor->capacidad_max;
    }

    /**
     * Zona activa (si existe) que cubre el destino del pedido.
     */
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

    private function pedidoResource(Pedido $pedido, ?Repartidor $repartidor = null): array
    {
        $distancia = null;

        if ($repartidor && $pedido->destino_lat !== null && $pedido->destino_lng !== null) {
            $distancia = $repartidor->distanciaA((float) $pedido->destino_lat, (float) $pedido->destino_lng);
        }

        // Calcular ganancia estimada para el repartidor (comisión sobre costo de envío)
        $costoEnvio      = $pedido->costoEnvioEfectivo();
        $gananciaEstimada = $repartidor ? $repartidor->gananciaEstimada($costoEnvio) : null;

        $items = $pedido->items->map(function ($item) {
            return [
                'id'               => $item->id,
                'producto_id'      => $item->producto_id,
                'nombre_producto'  => $item->nombre_producto,
                'precio_unitario'  => (float) $item->precio_unitario,
                'cantidad'         => (float) $item->cantidad,
                'subtotal'         => (float) $item->subtotal,
                'envase_retornable' => (bool) ($item->producto?->envase_retornable ?? false),
            ];
        })->all();

        return [
            'id'                => $pedido->id,
            'numero_pedido'     => $pedido->numero_pedido,
            'estado'            => $pedido->estado,
            'dispatch_mode'     => $pedido->dispatch_mode ?? 'internal',
            'delivery_type'     => $pedido->dispatch_mode ?? 'internal',
            'repartidor_id'     => $pedido->repartidor_id,
            'subtotal'          => (float) ($pedido->subtotal ?? 0),
            'impuesto'          => (float) ($pedido->impuesto ?? 0),
            'total'             => (float) $pedido->total,
            'costo_envio'       => $costoEnvio,
            'ganancia_estimada' => $gananciaEstimada,
            'metodo_pago'       => $pedido->metodo_pago,
            'nombre_cliente'    => $pedido->nombre_cliente,
            'telefono_cliente'  => $pedido->telefono_cliente,
            'direccion_cliente' => $pedido->direccion_cliente,
            'destino_lat'       => $pedido->destino_lat,
            'destino_lng'       => $pedido->destino_lng,
            'distancia_km'      => $distancia !== null ? round($distancia, 2) : null,
            'notas'             => $pedido->notas,
            'hora_aceptado'     => $pedido->hora_aceptado?->toISOString(),
            'hora_recogido'     => $pedido->hora_recogido?->toISOString(),
            'hora_entregado'    => $pedido->hora_entregado?->toISOString(),
            'created_at'        => $pedido->created_at?->toISOString(),
            'items'             => $items,
        ];
    }
}
