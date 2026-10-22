<?php

namespace App\Http\Middleware\Api;

use App\Support\AgentContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el token de API tenga al menos una de las abilities indicadas.
 *
 * Se usa junto al middleware `agent-api`, que deja el AgentContext resuelto
 * en $request->attributes->get('agent_context').
 *
 *   Route::get(...)->middleware(['agent-api', 'ability:agent:read']);
 *   Route::get(...)->middleware(['agent-api', 'ability:reports:read|catalog:read']);
 */
class RequireAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $context = $request->attributes->get('agent_context');

        if (! $context instanceof AgentContext) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        if ($abilities === []) {
            return $next($request);
        }

        foreach ($abilities as $ability) {
            foreach (explode('|', $ability) as $candidate) {
                if ($context->can(trim($candidate))) {
                    return $next($request);
                }
            }
        }

        return $this->jsonError(
            'El token no tiene los permisos requeridos para esta operacion.',
            403
        );
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
