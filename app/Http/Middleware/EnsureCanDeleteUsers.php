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
            'Solo el Master puede eliminar usuarios.'
        );

        return $next($request);
    }
}
