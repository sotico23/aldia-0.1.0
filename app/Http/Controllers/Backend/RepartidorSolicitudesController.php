<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

class RepartidorSolicitudesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.usuarios.viewAny', only: ['index']),
            new Middleware('permission:admin.usuarios.edit', only: ['aprobar', 'rechazar']),
        ];
    }

    /**
     * Lista las solicitudes de repartidores pendientes de aprobación.
     *
     * GET /api/v1/admin/repartidores/solicitudes
     */
    public function index(Request $request): JsonResponse
    {
        $ownerId = (int) $request->user()->getOwnerId();

        $solicitudes = Repartidor::where('approval_status', Repartidor::APPROVAL_PENDING)
            ->where('driver_type', Repartidor::TYPE_FREELANCER)
            ->with('user:id,name,email,rut,telefono,created_at')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Repartidor $r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'nombre' => $r->user?->name ?? 'Sin nombre',
                'rut' => $r->user?->rut ?? null,
                'email' => $r->user?->email ?? null,
                'telefono' => $r->user?->telefono ?? null,
                'fecha_registro' => $r->user?->created_at?->toISOString(),
                'documentos' => $r->documentos ?? [],
                'estado' => $r->estado,
                'approval_status' => $r->approval_status,
                'driver_type' => $r->driver_type,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $solicitudes,
        ]);
    }

    /**
     * Aprueba un repartidor solicitante.
     *
     * POST /api/v1/admin/repartidores/{id}/aprobar
     */
    public function aprobar(Request $request, int $id): JsonResponse
    {
        $repartidor = Repartidor::where('id', $id)
            ->where('approval_status', Repartidor::APPROVAL_PENDING)
            ->first();

        if (! $repartidor) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud de repartidor no encontrada o ya procesada.',
            ], 404);
        }

        $user = User::find($repartidor->user_id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario asociado no encontrado.',
            ], 404);
        }

        // Actualizar estado del repartidor
        $repartidor->update([
            'approval_status' => Repartidor::APPROVAL_APPROVED,
            'estado' => 'disponible',
            'rejected_reason' => null,
        ]);

        // Asignar rol Spatie de Repartidor al usuario
        $user->assignRole(Repartidor::defaultRole());

        // Emitir notificación en tiempo real al usuario
        Broadcast::channel('notifications.'.$user->id, function ($u) use ($user) {
            return (int) $u->id === (int) $user->id;
        });

        broadcast(new \App\Events\RepartidorEstadoActualizado($user, 'aprobado', 'Tu solicitud de repartidor ha sido aprobada. Ya puedes recibir entregas.'));

        return response()->json([
            'success' => true,
            'message' => 'Repartidor aprobado correctamente.',
            'data' => [
                'id' => $repartidor->id,
                'user_id' => $repartidor->user_id,
                'nombre' => $user->name,
                'email' => $user->email,
                'approval_status' => $repartidor->approval_status,
                'estado' => $repartidor->estado,
            ],
        ]);
    }

    /**
     * Rechaza un repartidor solicitante.
     *
     * POST /api/v1/admin/repartidores/{id}/rechazar
     */
    public function rechazar(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

        $repartidor = Repartidor::where('id', $id)
            ->where('approval_status', Repartidor::APPROVAL_PENDING)
            ->first();

        if (! $repartidor) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud de repartidor no encontrada o ya procesada.',
            ], 404);
        }

        $user = User::find($repartidor->user_id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario asociado no encontrado.',
            ], 404);
        }

        // Actualizar estado del repartidor
        $repartidor->update([
            'approval_status' => Repartidor::APPROVAL_REJECTED,
            'estado' => 'offline',
            'rejected_reason' => $validated['motivo'] ?? null,
        ]);

        // Emitir notificación en tiempo real al usuario
        broadcast(new \App\Events\RepartidorEstadoActualizado($user, 'rechazado', 'Tu solicitud de repartidor ha sido rechazada.'.($validated['motivo'] ? ' Motivo: '.$validated['motivo'] : '')));

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de repartidor rechazada.',
            'data' => [
                'id' => $repartidor->id,
                'user_id' => $repartidor->user_id,
                'nombre' => $user->name,
                'email' => $user->email,
                'approval_status' => $repartidor->approval_status,
                'estado' => $repartidor->estado,
                'rejected_reason' => $repartidor->rejected_reason,
            ],
        ]);
    }
}
