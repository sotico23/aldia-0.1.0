<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTenantToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only accept Bearer token from Authorization header (not query string)
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Token de API no proporcionado. Use la cabecera Authorization: Bearer <token>.',
            ], 401);
        }

        // Constant-time comparison to prevent timing attacks
        $user = User::whereNotNull('api_token')->get()->first(function ($u) use ($token) {
            return hash_equals((string) $u->api_token, (string) $token);
        });

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Token de API inválido.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta desactivada. Contacta al administrador.',
            ], 403);
        }

        $request->merge(['tenant' => $user]);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
