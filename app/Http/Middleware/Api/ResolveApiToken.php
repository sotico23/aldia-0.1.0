<?php

namespace App\Http\Middleware\Api;

use App\Models\User;
use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el Personal Access Token (Sanctum) de una peticion de la API.
 *
 * A diferencia de `auth:sanctum`, no delega la resolucion en el guard: el
 * token se busca explicitamente y queda disponible en el atributo
 * `api_token` de la peticion. Esto permite:
 *
 *  - Trabajar con las abilities exactas del token utilizado.
 *  - Revocar en `/logout` precisamente el token que se uso.
 *  - No depender del estado cacheado del guard (que en un contenedor
 *    persistente puede devolver el usuario de una peticion anterior).
 *
 * Este middleware NO acepta la sesion web: la API de agentes y la app
 * Flutter siempre usan un token.
 */
class ResolveApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! is_string($plainToken) || trim($plainToken) === '') {
            return $this->jsonError(
                'Token de API no proporcionado. Use la cabecera Authorization: Bearer <token>.',
                401
            );
        }

        $token = PersonalAccessToken::findToken($plainToken);

        if (! $token instanceof PersonalAccessToken) {
            return $this->jsonError('Token de API invalido o expirado.', 401);
        }

        if (app(ApiTokenService::class)->isExpired($token)) {
            return $this->jsonError('El token de API expiro. Inicia sesion nuevamente.', 401);
        }

        $user = $token->tokenable;

        if (! $user instanceof User) {
            return $this->jsonError('Token de API invalido o expirado.', 401);
        }

        if (! $user->is_active) {
            return $this->jsonError('Cuenta desactivada. Contacta al administrador.', 403);
        }

        if ($user->banned_at !== null) {
            return $this->jsonError('Cuenta bloqueada. Contacta al administrador.', 403);
        }

        $request->attributes->set('api_token', $token);
        $request->setUserResolver(fn (): User => $user->withAccessToken($token));

        // `Auth::user()` debe devolver el usuario del token (no el de la
        // sesion web) para que los global scopes de tenant sean coherentes.
        Auth::shouldUse('web');
        Auth::setUser($user);

        return $next($request);
    }

    /**
     * Error con el envelope de la API.
     */
    protected function jsonError(string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => [],
        ], $status);
    }
}
