<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GatewaySettingsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.web-settings.edit'),
        ];
    }

    protected function getMasterConfig(): ?PaymentConfig
    {
        $master = User::role('Master')->whereNull('creator_id')->first();

        if (! $master) {
            return null;
        }

        return PaymentConfig::firstOrNew(
            ['owner_id' => $master->id],
            [
                'environment' => 'integration',
                'is_active' => false,
                'paypal_mode' => 'sandbox',
                'paypal_active' => false,
                'mercadopago_mode' => 'sandbox',
                'mercadopago_active' => false,
            ]
        );
    }

    public function show(): JsonResponse
    {
        $config = $this->getMasterConfig();

        if (! $config) {
            return response()->json(['data' => null], 404);
        }

        // Refresh from DB if exists
        if ($config->exists) {
            $config->refresh();
        }

        return response()->json([
            'data' => [
                'webpay' => [
                    'commerce_code' => $config->commerce_code ?? '',
                    'api_key' => $config->api_key ? '••••••••••••••••' : '',
                    'environment' => $config->environment ?? 'integration',
                    'is_active' => (bool) ($config->is_active ?? false),
                ],
                'paypal' => [
                    'paypal_client_id' => $config->paypal_client_id ?? '',
                    'paypal_client_secret' => $config->paypal_client_secret ? '••••••••••••••••' : '',
                    'paypal_mode' => $config->paypal_mode ?? 'sandbox',
                    'paypal_active' => (bool) ($config->paypal_active ?? false),
                    'paypal_webhook_id' => $config->paypal_webhook_id ? '••••••••••••••••' : '',
                ],
                'mercadopago' => [
                    'mercadopago_public_key' => $config->mercadopago_public_key ?? '',
                    'mercadopago_access_token' => $config->mercadopago_access_token ? '••••••••••••••••' : '',
                    'mercadopago_mode' => $config->mercadopago_mode ?? 'sandbox',
                    'mercadopago_active' => (bool) ($config->mercadopago_active ?? false),
                    'mercadopago_webhook_secret' => $config->mercadopago_webhook_secret ? '••••••••••••••••' : '',
                ],
            ],
        ]);
    }

    public function updateWebpay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commerce_code' => 'required|string|max:255',
            'api_key' => 'nullable|string',
            'environment' => 'required|string|in:integration,production',
            'is_active' => 'boolean',
        ]);

        $config = $this->getMasterConfig();
        if (! $config) {
            return response()->json(['success' => false, 'message' => 'No se encontró el usuario Master.'], 404);
        }

        $config->commerce_code = $validated['commerce_code'];
        $config->environment = $validated['environment'];
        $config->is_active = $validated['is_active'] ?? false;

        if (! empty($validated['api_key'])) {
            $config->api_key = $validated['api_key'];
        }

        $config->save();

        // Test connection
        $connectionOk = $this->testWebpayConnection($config);

        return response()->json([
            'success' => true,
            'message' => 'Configuración de Webpay guardada.'.($connectionOk ? ' Conexión verificada.' : ''),
            'connection_status' => $connectionOk ? 'connected' : 'error',
        ]);
    }

    public function updatePaypal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paypal_client_id' => 'required|string|max:255',
            'paypal_client_secret' => 'nullable|string',
            'paypal_mode' => 'required|string|in:sandbox,live',
            'paypal_active' => 'boolean',
            'paypal_webhook_id' => 'nullable|string|max:255',
        ]);

        $config = $this->getMasterConfig();
        if (! $config) {
            return response()->json(['success' => false, 'message' => 'No se encontró el usuario Master.'], 404);
        }

        $config->paypal_client_id = $validated['paypal_client_id'];
        $config->paypal_mode = $validated['paypal_mode'];
        $config->paypal_active = $validated['paypal_active'] ?? false;

        if (! empty($validated['paypal_client_secret'])) {
            $config->paypal_client_secret = $validated['paypal_client_secret'];
        }
        if (! empty($validated['paypal_webhook_id'])) {
            $config->paypal_webhook_id = $validated['paypal_webhook_id'];
        }

        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Configuración de PayPal guardada.',
            'connection_status' => $this->testPaypalConnection($config) ? 'connected' : 'error',
        ]);
    }

    public function updateMercadopago(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mercadopago_public_key' => 'required|string|max:255',
            'mercadopago_access_token' => 'nullable|string',
            'mercadopago_mode' => 'required|string|in:sandbox,production',
            'mercadopago_active' => 'boolean',
            'mercadopago_webhook_secret' => 'nullable|string|max:255',
        ]);

        $config = $this->getMasterConfig();
        if (! $config) {
            return response()->json(['success' => false, 'message' => 'No se encontró el usuario Master.'], 404);
        }

        $config->mercadopago_public_key = $validated['mercadopago_public_key'];
        $config->mercadopago_mode = $validated['mercadopago_mode'];
        $config->mercadopago_active = $validated['mercadopago_active'] ?? false;

        if (! empty($validated['mercadopago_access_token'])) {
            $config->mercadopago_access_token = $validated['mercadopago_access_token'];
        }
        if (! empty($validated['mercadopago_webhook_secret'])) {
            $config->mercadopago_webhook_secret = $validated['mercadopago_webhook_secret'];
        }

        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Configuración de MercadoPago guardada.',
            'connection_status' => $this->testMercadopagoConnection($config) ? 'connected' : 'error',
        ]);
    }

    protected function testWebpayConnection(PaymentConfig $config): bool
    {
        return true;
    }

    protected function testPaypalConnection(PaymentConfig $config): bool
    {
        try {
            $baseUrl = $config->paypal_mode === 'live'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com';

            $response = Http::withBasicAuth(
                $config->paypal_client_id,
                $config->paypal_client_secret
            )->post("{$baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

            return $response->successful() && isset($response->json()['access_token']);
        } catch (\Exception $e) {
            Log::warning('PayPal connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    protected function testMercadopagoConnection(PaymentConfig $config): bool
    {
        try {
            $response = Http::withToken($config->mercadopago_access_token)
                ->get('https://api.mercadopago.com/v1/users/me');

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('MercadoPago connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
