<?php

namespace App\Http\Middleware;

use App\Models\AutomationConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-N8N-TOKEN');

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Token de n8n no proporcionado.',
            ], 401);
        }

        $businessId = $request->route('business');

        if ($businessId) {
            $businessToken = AutomationConfig::where('owner_id', $businessId)
                ->whereNotNull('n8n_token')
                ->value('n8n_token');

            if ($businessToken && hash_equals($businessToken, $token)) {
                return $next($request);
            }
        }

        $globalToken = config('services.n8n.token');

        if ($globalToken && hash_equals($globalToken, $token)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Token de n8n inválido.',
        ], 401);
    }
}
