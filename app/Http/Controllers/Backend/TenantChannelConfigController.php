<?php

namespace App\Http\Controllers\Backend;

use App\Events\ChannelConfigurationUpdated;
use App\Http\Controllers\Controller;
use App\Models\ChannelCredential;
use App\Services\N8nService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantChannelConfigController extends Controller
{
    protected const MASKED_API_KEY = '••••••••••••••••';

    public function show(): JsonResponse
    {
        $credentials = $this->tenantCredentials();

        return response()->json([
            'success' => true,
            'data' => [
                'n8n_base_url' => $credentials?->n8n_base_url ?? '',
                'n8n_telegram_proxy_webhook_url' => $credentials?->n8n_telegram_proxy_webhook_url ?? '',
                'has_api_key' => $credentials?->n8n_api_key !== null,
                'masked_api_key' => $credentials?->n8n_api_key ? self::MASKED_API_KEY : '',
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'n8n_base_url' => 'nullable|url|max:255',
            'n8n_telegram_proxy_webhook_url' => 'nullable|url|max:255',
            'n8n_api_key' => 'nullable|string|max:1024',
        ]);

        $ownerId = Auth::user()->getOwnerId();
        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

        $data = [
            'n8n_base_url' => $validated['n8n_base_url'] ?? null,
            'n8n_telegram_proxy_webhook_url' => $validated['n8n_telegram_proxy_webhook_url'] ?? null,
        ];

        $apiKey = $request->input('n8n_api_key');
        $hasExistingKey = $credentials?->n8n_api_key !== null;

        if ($apiKey !== null && $apiKey !== '' && $apiKey !== self::MASKED_API_KEY) {
            $data['n8n_api_key'] = $apiKey;
        } elseif ($credentials) {
            // Keep the previously stored API key when the field is empty or masked.
            $data['n8n_api_key'] = $credentials->n8n_api_key;
        } elseif (! $hasExistingKey && ($apiKey === '' || $apiKey === null)) {
            $data['n8n_api_key'] = null;
        }

        if ($credentials) {
            $credentials->update($data);
        } else {
            $data['owner_id'] = $ownerId;
            $credentials = ChannelCredential::create($data);
        }

        event(new ChannelConfigurationUpdated($ownerId, Auth::id(), 'custom'));

        return response()->json([
            'success' => true,
            'message' => 'Configuración de n8n guardada correctamente.',
            'data' => [
                'n8n_base_url' => $credentials->n8n_base_url ?? '',
                'n8n_telegram_proxy_webhook_url' => $credentials->n8n_telegram_proxy_webhook_url ?? '',
                'has_api_key' => $credentials->n8n_api_key !== null,
                'masked_api_key' => $credentials->n8n_api_key ? self::MASKED_API_KEY : '',
            ],
        ]);
    }

    public function testConnection(Request $request, N8nService $n8n): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

        $tenantBaseUrl = $request->input('n8n_base_url')
            ?: $credentials?->n8n_base_url;
        $tenantUrl = $request->input('n8n_telegram_proxy_webhook_url')
            ?: $credentials?->n8n_telegram_proxy_webhook_url;

        $result = $n8n->testTenantConnection($tenantUrl, $tenantBaseUrl, null, isTest: false);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    private function tenantCredentials(): ?ChannelCredential
    {
        return ChannelCredential::where('owner_id', Auth::user()->getOwnerId())->first();
    }
}
