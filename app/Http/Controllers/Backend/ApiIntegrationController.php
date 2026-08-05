<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ApiIntegration;
use App\Models\ChannelCredential;
use App\Scopes\OwnerScope;
use App\Services\N8nService;
use App\Services\TenantCredentialsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApiIntegrationController extends Controller
{
    public function __construct(protected TenantCredentialsService $credentialsService) {}

    public function index(): Response
    {
        $ownerId = Auth::user()->getOwnerId();

        $integraciones = ApiIntegration::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->get()
            ->map(fn (ApiIntegration $integration): array => $this->serialize($integration));

        $tenantChannel = ChannelCredential::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->first();

        return Inertia::render('Backend/Integraciones/Index', [
            'integraciones' => $integraciones,
            'n8n_config' => [
                'n8n_telegram_proxy_webhook_url' => $tenantChannel?->n8n_telegram_proxy_webhook_url ?? '',
                'has_api_key' => $tenantChannel?->n8n_api_key !== null,
                'masked_api_key' => $tenantChannel?->n8n_api_key ? TenantCredentialsService::MASKED : '',
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in(array_keys(ApiIntegration::PROVIDERS))],
            'environment' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'credentials' => ['required', 'array'],
        ]);

        $ownerId = Auth::user()->getOwnerId();

        $integration = $this->credentialsService->save(
            $ownerId,
            $validated['provider'],
            $validated['credentials'],
            $validated['environment'] ?? null,
            (bool) ($validated['is_active'] ?? false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Integración guardada correctamente.',
            'data' => $this->serialize($integration),
        ]);
    }

    public function test(string $provider, Request $request, N8nService $n8n): JsonResponse
    {
        if (! array_key_exists($provider, ApiIntegration::PROVIDERS)) {
            return response()->json([
                'success' => false,
                'message' => 'Proveedor no soportado.',
            ], 422);
        }

        $ownerId = Auth::user()->getOwnerId();

        $integration = ApiIntegration::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->where('provider', $provider)
            ->first();

        $credentials = $this->credentialsService->get($provider, $ownerId);

        if (empty($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Primero guarda las credenciales de este proveedor.',
            ], 422);
        }

        $result = $this->runProviderTest($provider, $credentials, $integration?->environment, $n8n);

        if ($integration) {
            $integration->update([
                'last_tested_at' => now(),
                'last_tested_status' => $result['success'] ? 'ok' : 'error',
                'last_tested_message' => $result['message'],
            ]);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Expose the tenant's decrypted credentials for autocomplete in other
     * views (gateways, canales, tienda). Scoped to the authenticated owner.
     */
    public function autocomplete(): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $data = [];

        foreach (array_keys(ApiIntegration::PROVIDERS) as $provider) {
            $credentials = $this->credentialsService->get($provider, $ownerId);

            if (! empty($credentials)) {
                $data[$provider] = $credentials;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * @return array{id: int, provider: string, category: string, environment: ?string, is_active: bool, credentials: array, last_tested_at: ?string, last_tested_status: ?string, last_tested_message: ?string}
     */
    private function serialize(ApiIntegration $integration): array
    {
        return [
            'id' => $integration->id,
            'provider' => $integration->provider,
            'category' => $integration->category(),
            'environment' => $integration->environment,
            'is_active' => $integration->is_active,
            'credentials' => $this->credentialsService->mask($integration->provider, $integration->credentials ?? []),
            'last_tested_at' => $integration->last_tested_at?->toISOString(),
            'last_tested_status' => $integration->last_tested_status,
            'last_tested_message' => $integration->last_tested_message,
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function runProviderTest(string $provider, array $credentials, ?string $environment, N8nService $n8n): array
    {
        try {
            return match ($provider) {
                'webpay' => $this->testWebpay($credentials),
                'mercadopago' => $this->testMercadoPago($credentials),
                'paypal' => $this->testPayPal($credentials, $environment),
                'n8n' => $this->testN8n($credentials, $n8n),
                'telegram' => $this->testTelegram($credentials),
                'whatsapp' => $this->testWhatsApp($credentials),
                'facebook_meta' => $this->testFacebookMeta($credentials),
                'shopify' => $this->testShopify($credentials),
                'woocommerce' => $this->testWoocommerce($credentials),
                'mercado_libre' => $this->testMercadoLibre($credentials),
                default => ['success' => false, 'message' => 'Proveedor no soportado.'],
            };
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => 'No se pudo conectar con el proveedor. Verifica tu conexión e inténtalo nuevamente.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al probar la conexión: '.$e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testWebpay(array $credentials): array
    {
        $commerceCode = (string) ($credentials['commerce_code'] ?? '');
        $apiKey = (string) ($credentials['api_key'] ?? '');

        if (! preg_match('/^\d{8}$/', $commerceCode)) {
            return ['success' => false, 'message' => 'El Commerce Code de Webpay debe tener 8 dígitos.'];
        }

        if ($apiKey === '' || $apiKey === TenantCredentialsService::MASKED) {
            return ['success' => false, 'message' => 'Falta la API Key de Webpay. Guárdala antes de probar la conexión.'];
        }

        return ['success' => true, 'message' => 'Credenciales de Webpay con formato válido. Guarda y verifica con una transacción real en el entorno correspondiente.'];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testMercadoPago(array $credentials): array
    {
        $token = (string) ($credentials['mercadopago_access_token'] ?? '');

        if ($token === '' || $token === TenantCredentialsService::MASKED) {
            return ['success' => false, 'message' => 'Falta el Access Token de Mercado Pago. Guárdalo antes de probar la conexión.'];
        }

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->withToken($token)
            ->get('https://api.mercadopago.com/users/me');

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Conexión exitosa con Mercado Pago (HTTP '.$response->status().').'];
        }

        return ['success' => false, 'message' => 'Mercado Pago rechazó el Access Token (HTTP '.$response->status().').'];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testPayPal(array $credentials, ?string $environment): array
    {
        $clientId = (string) ($credentials['paypal_client_id'] ?? '');
        $clientSecret = (string) ($credentials['paypal_client_secret'] ?? '');
        $mode = $environment ?: (string) ($credentials['paypal_mode'] ?? 'sandbox');

        if ($clientId === '' || $clientSecret === '' || $clientSecret === TenantCredentialsService::MASKED) {
            return ['success' => false, 'message' => 'Faltan el Client ID o Client Secret de PayPal. Guárdalos antes de probar la conexión.'];
        }

        $baseUrl = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($baseUrl.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if ($response->successful() && $response->json('access_token')) {
            return ['success' => true, 'message' => 'Conexión exitosa con PayPal (entorno '.$mode.').'];
        }

        return ['success' => false, 'message' => 'PayPal rechazó las credenciales (HTTP '.$response->status().').'];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testN8n(array $credentials, N8nService $n8n): array
    {
        $proxyUrl = (string) ($credentials['telegram_proxy_url'] ?? '');

        return $n8n->testTenantConnection($proxyUrl ?: null, isTest: false);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testTelegram(array $credentials): array
    {
        $token = (string) ($credentials['telegram_bot_token'] ?? '');

        if ($token === '' || $token === TenantCredentialsService::MASKED) {
            return ['success' => false, 'message' => 'Falta el Bot Token de Telegram. Guárdalo antes de probar la conexión.'];
        }

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->get('https://api.telegram.org/bot'.$token.'/getMe');

        $body = $response->json();

        if ($response->successful() && ($body['ok'] ?? false)) {
            $username = $body['result']['username'] ?? '';

            return ['success' => true, 'message' => 'Conexión exitosa con Telegram. Bot: @'.ltrim($username, '@')];
        }

        return ['success' => false, 'message' => 'El Bot Token de Telegram no es válido o expiró.'];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testWhatsApp(array $credentials): array
    {
        $token = (string) ($credentials['whatsapp_access_token'] ?? '');
        $phoneNumberId = (string) ($credentials['whatsapp_phone_number_id'] ?? '');
        $apiVersion = (string) ($credentials['whatsapp_api_version'] ?? 'v22.0');

        if ($token === '' || $token === TenantCredentialsService::MASKED) {
            return ['success' => false, 'message' => 'Falta el Access Token de WhatsApp. Guárdalo antes de probar la conexión.'];
        }

        $url = $phoneNumberId !== ''
            ? "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}"
            : "https://graph.facebook.com/{$apiVersion}/me";

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->withToken($token)
            ->get($url);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Conexión exitosa con WhatsApp (Graph API HTTP '.$response->status().').'];
        }

        return ['success' => false, 'message' => 'WhatsApp rechazó el Access Token (HTTP '.$response->status().').'];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testFacebookMeta(array $credentials): array
    {
        $token = (string) ($credentials['access_token'] ?? '');

        if ($token === '' || $token === TenantCredentialsService::MASKED) {
            return ['success' => false, 'message' => 'Falta el Access Token de Facebook/Meta. Guárdalo antes de probar la conexión.'];
        }

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->withToken($token)
            ->get('https://graph.facebook.com/v19.0/me', ['fields' => 'id,name']);

        if ($response->successful() && $response->json('id')) {
            return ['success' => true, 'message' => 'Conexión exitosa con Facebook/Meta. Usuario: '.$response->json('name', $response->json('id'))];
        }

        return ['success' => false, 'message' => 'Facebook/Meta rechazó el Access Token (HTTP '.$response->status().').'];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testShopify(array $credentials): array
    {
        $shopDomain = (string) ($credentials['shop_domain'] ?? '');
        $token = (string) ($credentials['access_token'] ?? '');
        $apiVersion = (string) ($credentials['api_version'] ?? '2024-10');

        if ($shopDomain === '' || $token === '' || $token === TenantCredentialsService::MASKED) {
            return ['success' => false, 'message' => 'Faltan el dominio de la tienda o el Access Token de Shopify. Guárdalos antes de probar la conexión.'];
        }

        $domain = str_contains($shopDomain, 'myshopify.com') ? $shopDomain : $shopDomain.'.myshopify.com';

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->withHeaders(['X-Shopify-Access-Token' => $token])
            ->get("https://{$domain}/admin/api/{$apiVersion}/shop.json");

        if ($response->successful()) {
            $shopName = $response->json('shop.name', $shopDomain);

            return ['success' => true, 'message' => 'Conexión exitosa con Shopify. Tienda: '.$shopName];
        }

        return ['success' => false, 'message' => 'Shopify rechazó las credenciales (HTTP '.$response->status().').'];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testWoocommerce(array $credentials): array
    {
        $storeUrl = rtrim((string) ($credentials['store_url'] ?? ''), '/');
        $consumerKey = (string) ($credentials['consumer_key'] ?? '');
        $consumerSecret = (string) ($credentials['consumer_secret'] ?? '');

        if ($storeUrl === '' || $consumerKey === '' || $consumerSecret === '' || $consumerSecret === TenantCredentialsService::MASKED) {
            return ['success' => false, 'message' => 'Faltan la URL de la tienda o las claves de WooCommerce. Guárdalas antes de probar la conexión.'];
        }

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->withBasicAuth($consumerKey, $consumerSecret)
            ->get($storeUrl.'/wp-json/wc/v3/system_status');

        if ($response->successful()) {
            $site = $response->json('environment.home_url', $storeUrl);

            return ['success' => true, 'message' => 'Conexión exitosa con WooCommerce. Sitio: '.$site];
        }

        return ['success' => false, 'message' => 'WooCommerce rechazó las credenciales (HTTP '.$response->status().').'];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testMercadoLibre(array $credentials): array
    {
        $token = (string) ($credentials['access_token'] ?? '');

        if ($token === '' || $token === TenantCredentialsService::MASKED) {
            return ['success' => false, 'message' => 'Falta el Access Token de Mercado Libre. Guárdalo antes de probar la conexión.'];
        }

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->withToken($token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->successful() && $response->json('id')) {
            return ['success' => true, 'message' => 'Conexión exitosa con Mercado Libre. Usuario: '.$response->json('nickname', $response->json('id'))];
        }

        return ['success' => false, 'message' => 'Mercado Libre rechazó el Access Token (HTTP '.$response->status().').'];
    }
}
