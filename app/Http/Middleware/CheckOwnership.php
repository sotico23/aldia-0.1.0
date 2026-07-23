<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOwnership
{
    public function handle(Request $request, Closure $next, string $resource = 'resource'): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Master and Super Admin can access any resource
        if ($user->hasRole('Master') || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        // Get the route model binding for the resource
        $model = $request->route($resource);

        if ($model && method_exists($model, 'getOwnerId')) {
            $modelOwnerId = $model->getOwnerId();
            if ($modelOwnerId !== null && $modelOwnerId !== $user->getOwnerId()) {
                abort(403, 'No tienes permiso para acceder a este recurso.');
            }
        }

        return $next($request);
    }
}
