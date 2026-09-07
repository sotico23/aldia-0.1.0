<?php
/**
 * Reverb Rate Limit Middleware - Rate limits WebSocket events
 */

namespace App\Http\Middleware;

use App\Services\ReverbRateLimiter;
use Closure;
use Illuminate\Http\Request;

class ReverbRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $limitType = 'default')
    {
        $user = $request->user();
        $identifier = $user ? $user->id : $request->ip();
        $key = "reverb:{$limitType}:{$identifier}";

        $rateLimiter = app(ReverbRateLimiter::class);
        
        if ($rateLimiter->checkLimit($key)) {
            return response()->json([
                'error' => 'Too Many Requests',
                'message' => 'Límite de tasa excedido. Intente de nuevo más tarde.',
                'retry_after' => $rateLimiter->remaining($key),
            ], 429);
        }

        $rateLimiter->increment($key);

        return $next($request);
    }
}