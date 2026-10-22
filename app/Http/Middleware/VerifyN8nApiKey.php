<?php

namespace App\Http\Middleware;

use App\Models\SystemIntegration;
use App\Models\User;
use App\Scopes\OwnerScope;
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

        // 1) API key del tenant (`users.n8n_api_key`).
        $owner = User::withoutGlobalScope(OwnerScope::class)
            ->whereNotNull('n8n_api_key')
            ->get()
            ->first(function ($user) use ($apiKey) {
                return hash_equals((string) $user->n8n_api_key, (string) $apiKey);
            });

        if ($owner) {
            // Attach business owner to request for downstream use
            $request->merge(['n8n_business_owner' => $owner]);

            return $next($request);
        }

        // 2) API key global de la integracion n8n (`system_integrations`).
        $integration = SystemIntegration::query()
            ->where('provider', 'n8n')
            ->where('is_active', true)
            ->get()
            ->first(function ($integration) use ($apiKey) {
                $key = (string) $integration->api_key;

                return $key !== '' && hash_equals($key, (string) $apiKey);
            });

        if ($integration) {
            $request->merge([
                'n8n_business_owner' => null,
                'n8n_global_integration' => $integration,
            ]);

            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'API Key inválida.',
        ], 401);
    }
}
