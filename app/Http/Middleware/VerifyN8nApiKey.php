<?php

namespace App\Http\Middleware;

use App\Models\SystemIntegration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API Key no proporcionada.',
            ], 401);
        }

        $config = SystemIntegration::forProvider('n8n')->first();

        if (! $config || ! $config->api_key || ! hash_equals($config->api_key, $apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'API Key inválida.',
            ], 401);
        }

        return $next($request);
    }
}
