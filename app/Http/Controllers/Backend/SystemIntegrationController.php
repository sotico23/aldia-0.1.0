<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SystemIntegration;
use App\Services\N8nService;
use App\Services\WhatsAppService;
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
                'telegram_proxy_url' => $config->telegram_proxy_url,
                'api_key' => $config->api_key ? '••••••••••••••••' : null,
                'whatsapp_phone_number_id' => $config->whatsapp_phone_number_id,
                'whatsapp_access_token' => $config->whatsapp_access_token ? '••••••••••••••••' : null,
                'whatsapp_business_id' => $config->whatsapp_business_id,
                'whatsapp_api_version' => $config->whatsapp_api_version ?? 'v22.0',
                'is_active' => $config->is_active,
                'last_check_at' => $config->last_check_at?->toIso8601String(),
                'last_check_status' => $config->last_check_status,
            ] : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'base_url' => 'nullable|url',
            'webhook_url' => 'nullable|url',
            'telegram_proxy_url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'whatsapp_phone_number_id' => 'nullable|string|max:50',
            'whatsapp_access_token' => 'nullable|string',
            'whatsapp_business_id' => 'nullable|string|max:50',
            'whatsapp_api_version' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $baseUrl = $validated['base_url'] ?? null;
        $webhookUrl = $validated['webhook_url'] ?? null;
        $telegramProxyUrl = $validated['telegram_proxy_url'] ?? null;
        $apiKey = $validated['api_key'] ?? null;
        $whatsappPhoneNumberId = $validated['whatsapp_phone_number_id'] ?? null;
        $whatsappAccessToken = $validated['whatsapp_access_token'] ?? null;
        $whatsappBusinessId = $validated['whatsapp_business_id'] ?? null;
        $whatsappApiVersion = $validated['whatsapp_api_version'] ?? null;

        $config = SystemIntegration::firstOrNew(['provider' => 'n8n']);

        if ($baseUrl !== null) {
            $config->base_url = rtrim($baseUrl, '/');
        } elseif (! $config->exists) {
            return response()->json([
                'success' => false,
                'message' => 'La URL Base es requerida al crear la configuración.',
            ], 422);
        }

        $config->is_active = $validated['is_active'] ?? false;

        if ($webhookUrl !== null) {
            $config->webhook_url = $webhookUrl;
        }

        if ($telegramProxyUrl !== null) {
            $config->telegram_proxy_url = $telegramProxyUrl;
        }

        if (! empty($apiKey)) {
            $config->api_key = $apiKey;
        } elseif (! $config->exists && empty($config->api_key)) {
            return response()->json([
                'success' => false,
                'message' => 'La API Key es requerida al crear la configuración.',
            ], 422);
        }

        if ($whatsappPhoneNumberId !== null) {
            $config->whatsapp_phone_number_id = $whatsappPhoneNumberId;
        }

        if (! empty($whatsappAccessToken)) {
            $config->whatsapp_access_token = $whatsappAccessToken;
        } elseif (! $config->exists && empty($config->whatsapp_access_token)) {
            // WhatsApp token is optional on create; only required if at least one WhatsApp field is provided
        }

        if ($whatsappBusinessId !== null) {
            $config->whatsapp_business_id = $whatsappBusinessId;
        }

        if ($whatsappApiVersion !== null) {
            $config->whatsapp_api_version = $whatsappApiVersion;
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
                'telegram_proxy_url' => $config->telegram_proxy_url,
                'api_key' => $config->api_key ? '••••••••••••••••' : null,
                'whatsapp_phone_number_id' => $config->whatsapp_phone_number_id,
                'whatsapp_access_token' => $config->whatsapp_access_token ? '••••••••••••••••' : null,
                'whatsapp_business_id' => $config->whatsapp_business_id,
                'whatsapp_api_version' => $config->whatsapp_api_version ?? 'v22.0',
                'is_active' => $config->is_active,
                'last_check_at' => $config->last_check_at?->toIso8601String(),
                'last_check_status' => $config->last_check_status,
            ],
        ]);
    }

    public function testConnection(N8nService $n8n): JsonResponse
    {
        $result = $n8n->testConnection();

        return response()->json($result, 200);
    }

    public function testWhatsAppConnection(Request $request, WhatsAppService $whatsApp): JsonResponse
    {
        $config = SystemIntegration::forProvider('n8n')->first();

        $phoneNumberId = $request->input('whatsapp_phone_number_id') ?? $config?->whatsapp_phone_number_id;
        $accessToken = $request->input('whatsapp_access_token') ?? $config?->whatsapp_access_token;
        $businessId = $request->input('whatsapp_business_id') ?? $config?->whatsapp_business_id;
        $apiVersion = $request->input('whatsapp_api_version') ?? $config?->whatsapp_api_version ?? 'v22.0';

        if (! $phoneNumberId) {
            return response()->json([
                'success' => false,
                'message' => 'No hay Phone Number ID de WhatsApp configurado.',
            ], 422);
        }

        if (! $accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'No hay Access Token de WhatsApp configurado.',
            ], 422);
        }

        $result = $whatsApp->validateCredentials($accessToken, $phoneNumberId);

        if ($result['success'] && $businessId) {
            $config?->update([
                'whatsapp_business_id' => $businessId,
            ]);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
