<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Variante JSON-aware de App\Http\Middleware\CheckOwnership.
 *
 * A diferencia de la version web (403), el acceso a un recurso de otro
 * tenant responde 404 para no filtrar la existencia del recurso.
 * Es la misma regla que aplica el Bot API.
 */
class CheckApiOwnership
{
    public function handle(Request $request, Closure $next, string $resource = 'resource'): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        // Master y Super Admin pueden acceder a cualquier recurso.
        if ($user->hasRole('Master') || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        $model = $request->route($resource);

        if ($model && method_exists($model, 'getOwnerId')) {
            $modelOwnerId = $model->getOwnerId();

            if ($modelOwnerId !== null && $modelOwnerId !== $user->getOwnerId()) {
                return $this->jsonError('Recurso no encontrado.', 404);
            }
        }

        return $next($request);
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => [],
        ], $status);
    }
}
