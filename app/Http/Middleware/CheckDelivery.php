<?php

namespace App\Http\Middleware;

use App\Models\Repartidor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: valida que el usuario autenticado tenga perfil de repartidor activo.
 *
 * Acepta tanto empleados (employee) como freelancers aprobados (approved).
 * Los freelancers en pending_approval reciben un 403 descriptivo.
 */
class CheckDelivery
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['success' => false, 'message' => 'Tu cuenta ha sido desactivada.'], 403);
        }

        if (! $user->hasRole(Repartidor::defaultRole())) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para acceder a la zona de repartidores.'], 403);
        }

        $repartidor = Repartidor::where('user_id', $user->id)->first();

        if (! $repartidor) {
            return response()->json(['success' => false, 'message' => 'No tienes un perfil de repartidor activo.'], 403);
        }

        // Freelancers deben estar aprobados para acceder a los endpoints de delivery
        if ($repartidor->isFreelancer() && ! $repartidor->isApproved()) {
            $mensaje = match ($repartidor->approval_status) {
                Repartidor::APPROVAL_PENDING => 'Tu perfil está pendiente de aprobación por el administrador.',
                Repartidor::APPROVAL_REJECTED => 'Tu solicitud fue rechazada. '.($repartidor->rejected_reason ?? ''),
                default => 'No puedes operar en este momento.',
            };

            return response()->json([
                'success' => false,
                'message' => $mensaje,
                'approval_status' => $repartidor->approval_status,
            ], 403);
        }

        $request->attributes->set('delivery.repartidor', $repartidor);

        return $next($request);
    }
}
