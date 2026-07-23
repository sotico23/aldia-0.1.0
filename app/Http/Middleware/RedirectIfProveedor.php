<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfProveedor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isProveedor()) {
            $isExcluded = $request->is('proveedor*') || $request->is('logout') || $request->is('mi-informacion*') || $request->is('api/global-search*') || $request->is('login*') || $request->is('register*');

            if (! $isExcluded) {
                return redirect()->route('proveedor.dashboard');
            }
        }

        return $next($request);
    }
}
