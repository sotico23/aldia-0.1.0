<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanDeleteUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user && $user->canDeletePlatformUsers(),
            403,
            'Solo el Master o un usuario con permisos de Panel Master / Control Total puede eliminar usuarios.'
        );

        return $next($request);
    }
}
