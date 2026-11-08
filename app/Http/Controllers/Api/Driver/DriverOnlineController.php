<?php

namespace App\Http\Controllers\Api\Driver;

use App\Events\DeliveryOrderPoolUpdated;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAsignacion;
use App\Models\DriverOrderOffer;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Scopes\OwnerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para el toggle Online/Offline del repartidor (modelo Uber).
 *
 * Separa la disponibilidad operativa (is_online: el repartidor quiere trabajar)
 * del estado de asignación (estado: disponible/ocupado/offline).
 *
 * is_online = true  → el repartidor está en la app listo para recibir pedidos
 * is_online = false → el repartidor se desconecta y deja de recibir ofertas
 */
class DriverOnlineController extends Controller
{
    /**
     * POST /api/v1/driver/toggle-online
     *
     * Alterna el estado Online/Offline del repartidor.
     * Body: { "is_online": true|false }
     */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_online' => ['required', 'boolean'],
        ]);

        $repartidor = $this->getRepartidor($request);

        if (! $repartidor) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un perfil de repartidor activo.',
            ], 404);
        }

        // Solo repartidores aprobados pueden conectarse
        if (! $repartidor->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => match ($repartidor->approval_status) {
                    'pending_approval' => 'Tu perfil está pendiente de aprobación.',
                    'rejected'         => 'Tu perfil fue rechazado. ' . ($repartidor->rejected_reason ?? ''),
                    default            => 'No puedes conectarte en este momento.',
                },
                'approval_status' => $repartidor->approval_status,
            ], 403);
        }

        $isOnline = (bool) $validated['is_online'];
        $updates  = [
            'is_online' => $isOnline,
        ];

        if ($isOnline) {
            // Al conectarse: pasar a disponible y registrar timestamp
            $updates['estado']        = 'disponible';
            $updates['last_online_at'] = now();
        } else {
            // Al desconectarse: cancelar ofertas pendientes y poner offline
            $updates['estado'] = 'offline';
            $this->cancelarOfertasPendientes($repartidor);
        }

        $repartidor->update($updates);

        return response()->json([
            'success'    => true,
            'message'    => $isOnline ? '¡Estás en línea! Comenzarás a recibir pedidos.' : 'Te has desconectado.',
            'is_online'  => $repartidor->is_online,
            'estado'     => $repartidor->estado,
            'last_online_at' => $repartidor->last_online_at?->toISOString(),
        ]);
    }

    /**
     * GET /api/v1/driver/offers
     *
     * Devuelve las ofertas de pedido pendientes para el repartidor.
     * Incluye datos de distancia y ganancia estimada.
     */
    public function offers(Request $request): JsonResponse
    {
        $repartidor = $this->getRepartidor($request);

        if (! $repartidor || ! $repartidor->isApproved() || ! $repartidor->is_online) {
            return response()->json([
                'success' => true,
                'offers'  => [],
            ]);
        }

        // Expirar ofertas vencidas automáticamente
        DriverOrderOffer::where('repartidor_id', $repartidor->id)
            ->where('status', DriverOrderOffer::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => DriverOrderOffer::STATUS_EXPIRED]);

        $ofertasPendientes = $this->ofertasPendientes($repartidor);

        if ($ofertasPendientes->isEmpty()) {
            $this->generarOfertasDesdePool($repartidor);
            $ofertasPendientes = $this->ofertasPendientes($repartidor);
        }

        $offers = $ofertasPendientes
            ->map(fn ($offer) => $this->offerResource($offer, $repartidor));

        return response()->json([
            'success' => true,
            'offers'  => $offers,
        ]);
    }

    /**
     * Ofertas pendientes vigentes del repartidor.
     */
    private function ofertasPendientes(Repartidor $repartidor)
    {
        return DriverOrderOffer::with('pedido.items')
            ->where('repartidor_id', $repartidor->id)
            ->where('status', DriverOrderOffer::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Genera ofertas para los pedidos del pool cercanos al repartidor.
     *
     * El modelo Uber/Rappi necesita que la alerta con countdown aparezca sin
     * esperar a que un proceso externo cree las ofertas. Se generan bajo
     * demanda al consultar `/driver/offers`, con un TTL corto y evitando
     * duplicados por pedido/repartidor.
     */
    private function generarOfertasDesdePool(Repartidor $repartidor): void
    {
        if ($repartidor->lat === null || $repartidor->lng === null) {
            return;
        }

        $creadas = 0;
        $limite = (int) config('delivery.offers_por_solicitud', 3);
        $ttl = (int) config('delivery.offer_ttl_seconds', 45);

        $pedidosQuery = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->disponibleEnPool()
            ->whereNotNull('destino_lat')
            ->whereNotNull('destino_lng');

        if ($repartidor->isFreelancer()) {
            // Freelancer: ve pedidos marketplace de cualquier empresa + pedidos de su propia empresa
            $pedidosQuery->where(function ($q) use ($repartidor) {
                $q->where('dispatch_mode', Pedido::DISPATCH_MARKETPLACE)
                    ->orWhere(function ($sub) use ($repartidor) {
                        $sub->where('owner_id', $repartidor->owner_id)
                            ->where(fn ($w) => $w->whereNull('dispatch_mode')->orWhere('dispatch_mode', Pedido::DISPATCH_INTERNAL));
                    });
            });
        } else {
            // Empleado: solo pedidos de su empresa
            $pedidosQuery->where('owner_id', $repartidor->owner_id);
        }

        $pedidos = $pedidosQuery->orderBy('created_at')
            ->limit(25)
            ->get();

        foreach ($pedidos as $pedido) {
            if ($creadas >= $limite) {
                break;
            }

            $distancia = $repartidor->distanciaA(
                (float) $pedido->destino_lat,
                (float) $pedido->destino_lng
            );

            if ($distancia === null || $distancia > (float) $repartidor->radio_km) {
                continue;
            }

            $yaOfertado = DriverOrderOffer::query()
                ->withoutGlobalScope(OwnerScope::class)
                ->where('repartidor_id', $repartidor->id)
                ->where('pedido_id', $pedido->id)
                ->exists();

            if ($yaOfertado) {
                continue;
            }

            DriverOrderOffer::create([
                'owner_id' => $pedido->owner_id,
                'pedido_id' => $pedido->id,
                'repartidor_id' => $repartidor->id,
                'status' => DriverOrderOffer::STATUS_PENDING,
                'distancia_km' => round($distancia, 2),
                'ganancia_estimada' => $repartidor->gananciaEstimada($pedido->costoEnvioEfectivo()),
                'expires_at' => now()->addSeconds($ttl),
            ]);

            $creadas++;
        }
    }

    /**
     * POST /api/v1/driver/offers/{offer}/respond
     *
     * El repartidor acepta o rechaza una oferta específica.
     * Body: { "action": "accept"|"reject", "reason": "..." }
     */
    public function respond(Request $request, DriverOrderOffer $offer): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:accept,reject'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $repartidor = $this->getRepartidor($request);

        // Verificar que la oferta le pertenece
        if (! $repartidor || (int) $offer->repartidor_id !== $repartidor->id) {
            return response()->json([
                'success' => false,
                'message' => 'Esta oferta no es para ti.',
            ], 403);
        }

        if (! $offer->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta oferta ya no está disponible.',
            ], 409);
        }

        if ($validated['action'] === 'reject') {
            $offer->update([
                'status'       => DriverOrderOffer::STATUS_REJECTED,
                'responded_at' => now(),
                'reject_reason' => $validated['reason'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Oferta rechazada.',
            ]);
        }

        // Acción: aceptar → se delega al flujo de accept existente en DeliveryOrdenController
        // Verificamos que el pedido siga disponible
        $pedido = Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->lockForUpdate()
            ->find($offer->pedido_id);

        if (! $pedido
            || ! in_array($pedido->estado, ['preparando', 'pendiente'], true)
            || $pedido->repartidor_id !== null
            || $pedido->pool_bloqueado
        ) {
            $offer->update([
                'status'       => DriverOrderOffer::STATUS_EXPIRED,
                'responded_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'El pedido ya no está disponible.',
            ], 409);
        }

        // Si el pedido es interno, validar que el repartidor pertenezca a la empresa
        if ($pedido->isInternal() && (int) $pedido->owner_id !== (int) $repartidor->owner_id) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido está reservado para la flota interna de la empresa.',
            ], 403);
        }

        // Asignar el pedido al repartidor
        $pedido->update([
            'repartidor_id' => $request->user()->id,
            'hora_aceptado' => now(),
        ]);

        // Registrar en asignaciones y emitir broadcast
        DeliveryAsignacion::registrar(
            $pedido,
            (int) $request->user()->id,
            null,
            (int) $request->user()->id,
            'aceptado_oferta'
        );

        broadcast(new DeliveryOrderPoolUpdated($pedido, 'aceptado'));

        // Cancelar otras ofertas pendientes para este pedido
        DriverOrderOffer::where('pedido_id', $offer->pedido_id)
            ->where('id', '!=', $offer->id)
            ->where('status', DriverOrderOffer::STATUS_PENDING)
            ->update(['status' => DriverOrderOffer::STATUS_CANCELLED]);

        $offer->update([
            'status'       => DriverOrderOffer::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        $repartidor->update(['estado' => 'disponible']);

        return response()->json([
            'success' => true,
            'message' => '¡Pedido aceptado!',
            'pedido'  => [
                'id'               => $pedido->id,
                'numero_pedido'    => $pedido->numero_pedido,
                'nombre_cliente'   => $pedido->nombre_cliente,
                'direccion_cliente' => $pedido->direccion_cliente,
                'destino_lat'      => $pedido->destino_lat,
                'destino_lng'      => $pedido->destino_lng,
                'total'            => (float) $pedido->total,
                'distancia_km'     => $offer->distancia_km ?? $pedido->distancia_km,
                'ganancia_estimada' => $offer->ganancia_estimada
                    ?? $repartidor->gananciaEstimada($pedido->costoEnvioEfectivo()),
            ],
        ]);
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    private function getRepartidor(Request $request): ?Repartidor
    {
        $cached = $request->attributes->get('delivery.repartidor');
        if ($cached instanceof Repartidor) {
            return $cached;
        }

        return Repartidor::where('user_id', $request->user()->id)->first();
    }

    private function cancelarOfertasPendientes(Repartidor $repartidor): void
    {
        DriverOrderOffer::where('repartidor_id', $repartidor->id)
            ->where('status', DriverOrderOffer::STATUS_PENDING)
            ->update([
                'status'       => DriverOrderOffer::STATUS_CANCELLED,
                'responded_at' => now(),
            ]);
    }

    private function offerResource(DriverOrderOffer $offer, ?Repartidor $repartidor = null): array
    {
        $pedido = $offer->pedido;
        $ganancia = (float) ($offer->ganancia_estimada ?? 0);

        if ($ganancia <= 0 && $repartidor && $pedido) {
            $ganancia = $repartidor->gananciaEstimada($pedido->costoEnvioEfectivo());
        }

        return [
            'offer_id'         => $offer->id,
            'pedido_id'        => $offer->pedido_id,
            'numero_pedido'    => $pedido?->numero_pedido,
            'nombre_cliente'   => $pedido?->nombre_cliente,
            'direccion_cliente' => $pedido?->direccion_cliente,
            'destino_lat'      => $pedido?->destino_lat,
            'destino_lng'      => $pedido?->destino_lng,
            'total'            => (float) ($pedido?->total ?? 0),
            'metodo_pago'      => $pedido?->metodo_pago,
            'distancia_km'     => $offer->distancia_km,
            'ganancia_estimada' => $ganancia,
            'expires_at'       => $offer->expires_at?->toISOString(),
            'segundos_restantes' => $offer->expires_at
                ? max(0, (int) now()->diffInSeconds($offer->expires_at, false))
                : null,
            'items_count'      => $pedido?->items?->count() ?? 0,
        ];
    }
}
