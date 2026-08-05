<?php

namespace App\Http\Controllers\Backend;

use App\Events\ChannelConfigurationUpdated;
use App\Http\Controllers\Controller;
use App\Models\AutomationConfig;
use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Models\WebSetting;
use App\Services\N8nService;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChannelCredentialController extends Controller
{
    public function index(): Response
    {
        $ownerId = Auth::user()->getOwnerId();
        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();
        $automation = AutomationConfig::where('owner_id', $ownerId)->first();
        $webSettings = WebSetting::getSettings();
        $n8nConfig = SystemIntegration::forProvider('n8n')->first();

        return Inertia::render('Backend/ChannelCredentials', [
            'credentials' => $credentials ? [
                'telegram_bot_token' => $credentials->telegram_bot_token ? '••••••••••••••••' : '',
                'telegram_bot_username' => $credentials->telegram_bot_username ?? '',
                'telegram_chat_id' => $credentials->telegram_chat_id ?? null,
                'bot_type' => $credentials->bot_type ?? null,
                'whatsapp_phone_number_id' => $credentials->whatsapp_phone_number_id ?? '',
                'whatsapp_access_token' => $credentials->whatsapp_access_token ? '••••••••••••••••' : '',
                'whatsapp_business_id' => $credentials->whatsapp_business_id ?? '',
                'whatsapp_api_version' => $credentials->whatsapp_api_version ?? 'v22.0',
            ] : null,
            'has_credentials' => $credentials !== null,
            'global_telegram_bot_username' => $webSettings->global_telegram_bot_username ?? null,
            'global_whatsapp' => $n8nConfig ? [
                'phone_number_id' => $n8nConfig->whatsapp_phone_number_id,
                'access_token' => $n8nConfig->whatsapp_access_token,
                'business_id' => $n8nConfig->whatsapp_business_id,
                'api_version' => $n8nConfig->whatsapp_api_version ?? 'v22.0',
                'is_active' => $n8nConfig->is_active,
            ] : null,
            'app_name' => $webSettings->app_name ?? 'Aldia',
            'automation' => $automation ? [
                'channel' => $automation->channel,
                'frequency' => $automation->frequency,
                'execution_time' => $automation->execution_time,
                'enabled' => $automation->enabled,
                'selected_reports' => $automation->selected_reports ?? [],
                'last_run_at' => $automation->last_run_at?->diffForHumans(),
                'next_run_at' => $automation->next_run_at?->diffForHumans(),
                'last_run_status' => $automation->last_run_status,
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_bot_username' => 'nullable|string|max:255',
            'bot_type' => 'nullable|string|in:global,custom',
            'whatsapp_phone_number_id' => 'nullable|string|max:50',
            'whatsapp_access_token' => 'nullable|string',
            'whatsapp_business_id' => 'nullable|string|max:50',
            'whatsapp_api_version' => 'nullable|string|max:20',
        ]);

        $validated = array_filter($validated, fn ($value) => $value !== null);

        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

        if ($credentials) {
            $fillable = $credentials->getFillable();
            $updateData = array_intersect_key($validated, array_flip($fillable));

            if ($request->filled('telegram_bot_token') && $request->input('telegram_bot_token') !== '••••••••••••••••') {
                $updateData['telegram_bot_token'] = $request->input('telegram_bot_token');
            } elseif (! $request->filled('telegram_bot_token') || $request->input('telegram_bot_token') === '••••••••••••••••') {
                unset($updateData['telegram_bot_token']);
            }

            if ($request->filled('whatsapp_access_token') && $request->input('whatsapp_access_token') !== '••••••••••••••••') {
                $updateData['whatsapp_access_token'] = $request->input('whatsapp_access_token');
            } elseif (! $request->filled('whatsapp_access_token') || $request->input('whatsapp_access_token') === '••••••••••••••••') {
                unset($updateData['whatsapp_access_token']);
            }

            $credentials->update($updateData);
        } else {
            $data = array_merge($validated, ['owner_id' => $ownerId]);
            ChannelCredential::create($data);
        }

        if ($credentials?->telegram_bot_token) {
            $telegramService = new TelegramService;
            $telegramService->forOwner($ownerId);
            $telegramService->setWebhook(route('webhooks.telegram'));
        }

        event(new ChannelConfigurationUpdated($ownerId, Auth::id(), 'custom'));

        return redirect()->route('channel-credentials.index')
            ->with('success', 'Credenciales guardadas correctamente.');
    }

    public function testTelegram(Request $request): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $token = $request->input('telegram_bot_token');

        if (! $token) {
            $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

            if (! $credentials || ! $credentials->telegram_bot_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay un Token de Bot de Telegram configurado. Ingresa un token o guárdalo primero.',
                ]);
            }

            $token = $credentials->telegram_bot_token;
        }

        $service = new TelegramService;
        $result = $service->validateCredentials($token);

        if (! ($result['success'] ?? false)) {
            return response()->json($result);
        }

        $webhookUrl = route('webhooks.telegram');
        $service->setWebhook($webhookUrl);

        $n8nResult = (new N8nService)->testTelegramProxy();

        return response()->json([
            'success' => $n8nResult['success'],
            'message' => ($result['message'] ?? 'Credenciales de Telegram válidas.')
                .' '
                .$n8nResult['message'],
            'bot_username' => $result['bot_username'] ?? null,
            'bot_id' => $result['bot_id'] ?? null,
            'bot_name' => $result['bot_name'] ?? null,
        ]);
    }

    public function sendTestMessage(Request $request): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

        if (! $credentials || ! $credentials->telegram_bot_token) {
            return response()->json([
                'success' => false,
                'message' => 'No hay un Token de Bot de Telegram configurado.',
            ]);
        }

        if (! $credentials->telegram_chat_id) {
            return response()->json([
                'success' => false,
                'message' => 'No se ha vinculado una cuenta de Telegram. Primero abre el chat del bot y presiona "Iniciar" (/start) o usa el widget de inicio de sesión de Telegram.',
            ]);
        }

        $service = new TelegramService;
        $service->forOwner($ownerId);
        $result = $service->sendMessage($credentials->telegram_chat_id, '🤖 Mensaje de prueba desde Aldia');

        return response()->json($result);
    }

    public function testWhatsApp(): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

        if (! $credentials || ! $credentials->whatsapp_access_token || ! $credentials->whatsapp_phone_number_id) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales de WhatsApp no configuradas.',
            ]);
        }

        $service = (new WhatsAppService)->forOwner($ownerId);

        $result = $service->validateCredentials(
            $credentials->whatsapp_access_token,
            $credentials->whatsapp_phone_number_id,
        );

        return response()->json($result);
    }

    public function sendWhatsAppTestMessage(Request $request): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $phoneNumberId = $request->input('whatsapp_phone_number_id');

        if (! $phoneNumberId) {
            $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

            if (! $credentials || ! $credentials->whatsapp_phone_number_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay un Phone Number ID de WhatsApp configurado.',
                ]);
            }

            $phoneNumberId = $credentials->whatsapp_phone_number_id;
        }

        $to = $request->input('whatsapp_to');

        if (! $to) {
            return response()->json([
                'success' => false,
                'message' => 'Debes proporcionar un número de WhatsApp destino.',
            ]);
        }

        $service = (new WhatsAppService)->forOwner($ownerId);
        $result = $service->sendMessage($to, '🤖 Mensaje de prueba desde Aldia');

        return response()->json($result);
    }
}
