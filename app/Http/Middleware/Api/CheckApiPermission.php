<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Variante JSON-aware de App\Http\Middleware\CheckPermission.
 *
 * - Sin usuario -> 401 JSON (la version web redirige a /login).
 * - Sin permiso -> 403 JSON.
 * - Trial expirado en acciones de escritura -> 403 JSON { trial_expired: true }.
 */
class CheckApiPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        // Master y Super Admin bypassean las comprobaciones de permiso.
        if ($user->hasRole('Master') || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        $hasPermission = false;

        foreach ($permissions as $permissionGroup) {
            foreach (explode('|', $permissionGroup) as $permission) {
                if ($user->can(trim($permission))) {
                    $hasPermission = true;
                    break 2;
                }
            }
        }

        if (! $hasPermission) {
            return $this->jsonError('No tienes permiso para acceder a esta seccion.', 403);
        }

        if ($user->isTrialActive()) {
            return $next($request);
        }

        if ($user->isTrialExpired() && $this->isWriteAction($permissions)) {
            return response()->json([
                'status' => 'error',
                'trial_expired' => true,
                'message' => 'Tu periodo de prueba ha finalizado. Actualiza tu plan para seguir editando.',
                'errors' => [],
            ], 403);
        }

        return $next($request);
    }

    private function isWriteAction(array $permissions): bool
    {
        $writeActions = ['create', 'edit', 'delete', 'import', 'export'];

        foreach ($permissions as $permissionGroup) {
            foreach (explode('|', $permissionGroup) as $permission) {
                $parts = explode('.', trim($permission));
                $action = end($parts);

                if (in_array($action, $writeActions, true)) {
                    return true;
                }
            }
        }

        return false;
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
