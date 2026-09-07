<?php

namespace App\Http\Middleware;

use App\Models\Repartidor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        if (! $user->hasRole('Repartidor')) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para acceder a la zona de repartidores.'], 403);
        }

        $repartidor = Repartidor::where('user_id', $user->id)->first();

        if (! $repartidor) {
            return response()->json(['success' => false, 'message' => 'No tienes un perfil de repartidor activo.'], 403);
        }

        $request->attributes->set('delivery.repartidor', $repartidor);

        return $next($request);
    }
}
