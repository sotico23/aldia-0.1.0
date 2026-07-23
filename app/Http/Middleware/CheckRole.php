<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$parameters): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Master and Super Admin have access to everything
        if ($user->hasRole('Master') || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        // Parse parameters - can be roles (role:xxx) or permissions (permission:xxx)
        $roles = [];
        $permissions = [];

        foreach ($parameters as $param) {
            if (str_starts_with($param, 'role:')) {
                $roleList = str_replace('role:', '', $param);
                $roles = array_merge($roles, explode('|', $roleList));
            } elseif (str_starts_with($param, 'permission:')) {
                $permList = str_replace('permission:', '', $param);
                $permissions = array_merge($permissions, explode('|', $permList));
            } else {
                // Assume it's a role if no prefix
                $roles = array_merge($roles, explode('|', $param));
            }
        }

        // Check roles first
        if (! empty($roles)) {
            foreach ($roles as $role) {
                if ($user->hasRole(trim($role))) {
                    return $next($request);
                }
            }
        }

        // Then check permissions if no role matched
        if (! empty($permissions)) {
            foreach ($permissions as $permission) {
                if ($user->can(trim($permission))) {
                    return $next($request);
                }
            }
        }

        abort(403, 'No tienes permiso para acceder a esta sección.');
    }
}
