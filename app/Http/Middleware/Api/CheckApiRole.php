<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Variante JSON-aware de App\Http\Middleware\CheckRole.
 *
 * El middleware web redirige a /login cuando no hay usuario, lo que devuelve
 * HTML a un cliente movil / agente. Esta version responde siempre JSON.
 *
 * Sintaxis de parametros (identica a la version web):
 *   role:X|Y           -> alguno de los roles
 *   permission:X|Y     -> alguno de los permisos
 *   X|Y                -> se interpreta como roles
 */
class CheckApiRole
{
    public function handle(Request $request, Closure $next, string ...$parameters): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->jsonError('Unauthenticated.', 401);
        }

        // Master y Super Admin tienen acceso a todo.
        if ($user->hasRole('Master') || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        $roles = [];
        $permissions = [];

        foreach ($parameters as $param) {
            if (str_starts_with($param, 'role:')) {
                $roles = array_merge($roles, explode('|', str_replace('role:', '', $param)));
            } elseif (str_starts_with($param, 'permission:')) {
                $permissions = array_merge($permissions, explode('|', str_replace('permission:', '', $param)));
            } else {
                $roles = array_merge($roles, explode('|', $param));
            }
        }

        foreach ($roles as $role) {
            if ($user->hasRole(trim($role))) {
                return $next($request);
            }
        }

        foreach ($permissions as $permission) {
            if ($user->can(trim($permission))) {
                return $next($request);
            }
        }

        return $this->jsonError('No tienes permiso para acceder a esta seccion.', 403);
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
