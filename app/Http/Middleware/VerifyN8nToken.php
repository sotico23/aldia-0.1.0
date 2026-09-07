<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Scopes\OwnerScope;
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

        // If route has {business} parameter, verify token matches that business
        if ($businessId) {
            $owner = User::withoutGlobalScope(OwnerScope::class)
                ->where('id', $businessId)
                ->whereNotNull('n8n_api_key')
                ->first();

            if (! $owner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Negocio no encontrado o sin API key configurada.',
                ], 404);
            }

            if (! hash_equals((string) $owner->n8n_api_key, (string) $token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de n8n inválido para este negocio.',
                ], 401);
            }
        } else {
            // For routes without {business} param (e.g., check-linking), find owner by token
            $owner = User::withoutGlobalScope(OwnerScope::class)
                ->whereNotNull('n8n_api_key')
                ->get()
                ->first(function ($user) use ($token) {
                    return hash_equals((string) $user->n8n_api_key, (string) $token);
                });

            if (! $owner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de n8n inválido.',
                ], 401);
            }
        }

        // Attach business owner to request for downstream use
        $request->merge(['n8n_business_owner' => $owner]);

        return $next($request);
    }
}
