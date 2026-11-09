<?php

namespace App\Http\Controllers\Backend;

use App\Events\ChannelConfigurationUpdated;
use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use App\Models\User;
use App\Services\OfficialChannelService;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class GatewaySettingsController extends Controller implements HasMiddleware
{
    private const SERVICE_SETTINGS_GROUPS = [
        'app',
        'redis',
        'mail',
        'aws',
        'reverb',
        'openrouter',
        'sii',
    ];

    public function __construct(protected OfficialChannelService $officialChannels) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.web-settings.edit'),
        ];
    }

    protected function getMasterConfig(): ?PaymentConfig
    {
        $master = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Master'))
            ->whereNull('creator_id')
            ->first();

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

        // Refresh from DB if exists
        if ($config?->exists) {
            $config->refresh();
        }

        return response()->json([
            'data' => [
                'webpay' => [
                    'commerce_code' => $config?->commerce_code ?? '',
                    'api_key' => $config?->api_key ? '••••••••••••••••' : '',
                    'environment' => $config?->environment ?? 'integration',
                    'is_active' => (bool) ($config?->is_active ?? false),
                ],
                'paypal' => [
                    'paypal_client_id' => $config?->paypal_client_id ?? '',
                    'paypal_client_secret' => $config?->paypal_client_secret ? '••••••••••••••••' : '',
                    'paypal_mode' => $config?->paypal_mode ?? 'sandbox',
                    'paypal_active' => (bool) ($config?->paypal_active ?? false),
                    'paypal_webhook_id' => $config?->paypal_webhook_id ? '••••••••••••••••' : '',
                ],
                'mercadopago' => [
                    'mercadopago_public_key' => $config?->mercadopago_public_key ?? '',
                    'mercadopago_access_token' => $config?->mercadopago_access_token ? '••••••••••••••••' : '',
                    'mercadopago_mode' => $config?->mercadopago_mode ?? 'sandbox',
                    'mercadopago_active' => (bool) ($config?->mercadopago_active ?? false),
                    'mercadopago_webhook_secret' => $config?->mercadopago_webhook_secret ? '••••••••••••••••' : '',
                ],
                'telegram' => $this->officialChannels->telegramPayload(),
                'whatsapp' => $this->officialChannels->whatsAppPayload(),
                'system_settings' => $this->serviceSettingsPayload(),
            ],
        ]);
    }

    public function updateServiceSettings(Request $request): JsonResponse
    {
        $fields = $this->serviceSettingsFields();
        $valueKeys = [];
        $secretKeys = [];

        foreach ($fields as $key => $field) {
            if ($field['secret'] ?? false) {
                $secretKeys[] = $key;
            } else {
                $valueKeys[] = $key;
            }
        }

        $rules = [
            'values' => ['required', 'array:'.implode(',', $valueKeys)],
            'secrets' => ['sometimes', 'array:'.implode(',', $secretKeys)],
        ];

        foreach ($fields as $key => $field) {
            if ($field['secret'] ?? false) {
                $rules["secrets.{$key}"] = ['nullable', 'string', 'max:4000'];

                continue;
            }

            $rules["values.{$key}"] = match ($field['type'] ?? 'text') {
                'number' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'email' => ['nullable', 'email', 'max:255'],
                'url' => ['nullable', 'url', 'max:2048'],
                'select' => ['nullable', 'string', Rule::in(array_keys($field['options'] ?? []))],
                default => ['nullable', 'string', 'max:2000'],
            };
        }

        $validated = $request->validate($rules);
        $entries = [];

        foreach ($fields as $key => $field) {
            if ($field['secret'] ?? false) {
                $secret = $validated['secrets'][$key] ?? null;

                if (filled($secret)) {
                    $entries[$key] = [
                        'value' => $secret,
                        'secret' => true,
                        'group' => $field['group'],
                    ];
                }

                continue;
            }

            if (array_key_exists($key, $validated['values'])) {
                $entries[$key] = [
                    'value' => $validated['values'][$key],
                    'secret' => false,
                    'group' => $field['group'],
                ];
            }
        }

        SystemSettings::save($entries);
        SystemSettings::applyToConfig();

        return response()->json([
            'success' => true,
            'message' => 'Configuración de servicios guardada correctamente.',
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

    public function updateTelegram(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bot_token' => 'nullable|string',
            'bot_username' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $this->officialChannels->updateTelegram($validated);

        event(new ChannelConfigurationUpdated(
            Auth::user()->getOwnerId(),
            Auth::id(),
            'global'
        ));

        return response()->json([
            'success' => true,
            'message' => 'Configuración del bot oficial de Telegram guardada.',
            'data' => $this->officialChannels->telegramPayload(),
        ]);
    }

    public function testTelegram(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bot_token' => 'nullable|string',
            'bot_username' => 'nullable|string|max:255',
        ]);

        $result = $this->officialChannels->testTelegram(
            $validated['bot_token'] ?? null,
            $validated['bot_username'] ?? null,
        );

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function setTelegramWebhook(): JsonResponse
    {
        $result = $this->officialChannels->setTelegramWebhook();

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function deleteTelegramWebhook(): JsonResponse
    {
        $result = $this->officialChannels->deleteTelegramWebhook();

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function updateWhatsApp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number_id' => 'nullable|string|max:50',
            'access_token' => 'nullable|string',
            'business_id' => 'nullable|string|max:50',
            'api_version' => 'nullable|string|max:20',
            'verify_token' => 'nullable|string|max:255',
            'app_secret' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $this->officialChannels->updateWhatsApp($validated);

        return response()->json([
            'success' => true,
            'message' => 'Configuración de WhatsApp oficial guardada.',
            'data' => $this->officialChannels->whatsAppPayload(),
        ]);
    }

    public function testWhatsApp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => 'nullable|string',
            'phone_number_id' => 'nullable|string|max:50',
        ]);

        $result = $this->officialChannels->testWhatsApp(
            $validated['access_token'] ?? null,
            $validated['phone_number_id'] ?? null,
        );

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function setWhatsAppWebhook(): JsonResponse
    {
        $result = $this->officialChannels->setWhatsAppWebhook();

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
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

    /**
     * @return array<string, array{label: string, description: string, fields: array<string, array<string, mixed>>}>
     */
    private function serviceSettingsPayload(): array
    {
        $stored = SystemSettings::values();
        $groups = [];

        foreach (self::SERVICE_SETTINGS_GROUPS as $groupId) {
            $group = SystemSettings::groups()[$groupId] ?? null;

            if ($group === null) {
                continue;
            }

            $fields = [];

            foreach ($group['fields'] as $key => $field) {
                $isSecret = (bool) ($field['secret'] ?? false);
                $fieldPayload = [
                    'key' => $key,
                    'label' => $field['label'] ?? $key,
                    'type' => $field['type'] ?? 'text',
                    'options' => $field['options'] ?? [],
                    'help' => $field['help'] ?? null,
                    'secret' => $isSecret,
                    'configured' => $isSecret
                        ? filled($stored[$key] ?? null) || SystemSettings::envHasValue($key)
                        : null,
                ];

                if (! $isSecret) {
                    $fieldPayload['value'] = (string) ($stored[$key] ?? $field['default'] ?? '');
                }

                $fields[$key] = $fieldPayload;
            }

            $groups[$groupId] = [
                'label' => $group['label'] ?? $groupId,
                'description' => $group['description'] ?? '',
                'fields' => $fields,
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function serviceSettingsFields(): array
    {
        $fields = [];

        foreach (self::SERVICE_SETTINGS_GROUPS as $groupId) {
            foreach (SystemSettings::groups()[$groupId]['fields'] ?? [] as $key => $field) {
                $fields[$key] = [...$field, 'group' => $groupId];
            }
        }

        return $fields;
    }
}
