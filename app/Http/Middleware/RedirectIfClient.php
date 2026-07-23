<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfClient
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isCliente()) {
            // If accessing administrative or non-client routes, redirect to client dashboard
            if (! $request->is('cliente*') && ! $request->is('logout') && ! $request->is('mi-informacion*') && ! $request->is('api/global-search*') && ! $request->is('login*') && ! $request->is('register*')) {
                return redirect()->route('cliente.dashboard');
            }
        }

        return $next($request);
    }
}
