<?php

namespace App\Http\Middleware;

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

        // Find business owner by API key
        $owner = User::withoutGlobalScope(OwnerScope::class)
            ->whereNotNull('n8n_api_key')
            ->get()
            ->first(function ($user) use ($apiKey) {
                return hash_equals((string) $user->n8n_api_key, (string) $apiKey);
            });

        if (! $owner) {
            return response()->json([
                'success' => false,
                'message' => 'API Key inválida.',
            ], 401);
        }

        // Attach business owner to request for downstream use
        $request->merge(['n8n_business_owner' => $owner]);

        return $next($request);
    }
}
