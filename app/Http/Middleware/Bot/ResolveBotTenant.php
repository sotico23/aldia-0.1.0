<?php

namespace App\Http\Middleware\Bot;

use App\Models\User;
use App\Support\BotContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBotTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->jsonError('Token de API no proporcionado. Use la cabecera Authorization: Bearer <token>.', 401);
        }

        $apiUser = User::where('api_token', $token)->first();

        if (! $apiUser) {
            return $this->jsonError('Token de API inválido.', 401);
        }

        if (! $apiUser->is_active) {
            return $this->jsonError('Cuenta desactivada. Contacta al administrador.', 403);
        }

        $ownerId = $request->header('X-Owner-ID');

        if ($ownerId === null || ! ctype_digit((string) $ownerId)) {
            return $this->jsonError('Cabecera X-Owner-ID obligatoria y debe ser numérica.', 401);
        }

        $ownerId = (int) $ownerId;

        $isPlatformUser = $apiUser->hasRole('Master') || $apiUser->hasRole('Super Admin');

        if (! $isPlatformUser && $apiUser->getOwnerId() !== $ownerId) {
            return $this->jsonError('El token no pertenece al tenant indicado en X-Owner-ID.', 401);
        }

        $tenant = User::find($ownerId);

        if (! $tenant || ! $tenant->is_active || $tenant->getOwnerId() !== $ownerId) {
            return $this->jsonError('Tenant inválido o inactivo en X-Owner-ID.', 401);
        }

        $actingUserId = $tenant->id;

        if ($request->hasHeader('X-User-ID')) {
            $actingUser = User::find((int) $request->header('X-User-ID'));

            if (! $actingUser || ! $actingUser->is_active || $actingUser->getOwnerId() !== $ownerId) {
                return $this->jsonError('X-User-ID inválido o no pertenece al tenant.', 401);
            }

            $actingUserId = $actingUser->id;
        }

        $request->attributes->set('bot_context', new BotContext($ownerId, $actingUserId));
        $request->setUserResolver(fn () => $tenant);

        return $next($request);
    }

    protected function jsonError(string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => [],
        ], $status);
    }
}
