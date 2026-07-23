<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SystemIntegration;
use App\Services\N8nService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SystemIntegrationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.web-settings.edit'),
        ];
    }

    public function show(): JsonResponse
    {
        $config = SystemIntegration::forProvider('n8n')->first();

        return response()->json([
            'data' => $config ? [
                'id' => $config->id,
                'provider' => $config->provider,
                'base_url' => $config->base_url,
                'webhook_url' => $config->webhook_url,
                'api_key' => $config->api_key ? '••••••••••••••••' : null,
                'is_active' => $config->is_active,
                'last_check_at' => $config->last_check_at?->toIso8601String(),
                'last_check_status' => $config->last_check_status,
            ] : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'base_url' => 'required|url',
            'webhook_url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $config = SystemIntegration::firstOrNew(['provider' => 'n8n']);
        $config->base_url = rtrim($validated['base_url'], '/');
        $config->is_active = $validated['is_active'] ?? false;

        if (! empty($validated['webhook_url'])) {
            $config->webhook_url = $validated['webhook_url'];
        }

        if (! empty($validated['api_key'])) {
            $config->api_key = $validated['api_key'];
        } elseif (! $config->exists) {
            return response()->json([
                'success' => false,
                'message' => 'La API Key es requerida al crear la configuración.',
            ], 422);
        }

        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Configuración de n8n guardada correctamente.',
            'data' => [
                'id' => $config->id,
                'provider' => $config->provider,
                'base_url' => $config->base_url,
                'webhook_url' => $config->webhook_url,
                'api_key' => $config->api_key ? '••••••••••••••••' : null,
                'is_active' => $config->is_active,
                'last_check_at' => $config->last_check_at?->toIso8601String(),
                'last_check_status' => $config->last_check_status,
            ],
        ]);
    }

    public function testConnection(N8nService $n8n): JsonResponse
    {
        $result = $n8n->testConnection();

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
