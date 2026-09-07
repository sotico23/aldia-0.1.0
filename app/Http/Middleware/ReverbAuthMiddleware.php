<?php
/**
 * Reverb Auth Middleware - Authenticates WebSocket connections
 */

namespace App\Http\Middleware;

use App\Services\ReverbAuthService;
use Closure;
use Illuminate\Http\Request;

class ReverbAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if (! $token) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token de acceso requerido',
            ], 401);
        }

        $authService = app(ReverbAuthService::class);
        $user = $authService->getUserFromToken($token);
        
        if (! $user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token inválido o expirado',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);
        $request->merge(['reverb_user' => $user]);

        return $next($request);
    }
}