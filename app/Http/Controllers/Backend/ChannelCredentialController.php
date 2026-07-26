<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AutomationConfig;
use App\Models\ChannelCredential;
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

        return Inertia::render('Backend/ChannelCredentials', [
            'credentials' => $credentials ? [
                'telegram_bot_token' => $credentials->telegram_bot_token ? '••••••••••••••••' : '',
                'telegram_bot_username' => $credentials->telegram_bot_username ?? '',
                'whatsapp_phone_number_id' => $credentials->whatsapp_phone_number_id ?? '',
                'whatsapp_access_token' => $credentials->whatsapp_access_token ? '••••••••••••••••' : '',
                'whatsapp_business_id' => $credentials->whatsapp_business_id ?? '',
                'whatsapp_api_version' => $credentials->whatsapp_api_version ?? 'v22.0',
            ] : null,
            'has_credentials' => $credentials !== null,
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
            } elseif (! $request->filled('telegram_bot_token')) {
                unset($updateData['telegram_bot_token']);
            } else {
                unset($updateData['telegram_bot_token']);
            }

            if ($request->filled('whatsapp_access_token') && $request->input('whatsapp_access_token') !== '••••••••••••••••') {
                $updateData['whatsapp_access_token'] = $request->input('whatsapp_access_token');
            } elseif (! $request->filled('whatsapp_access_token')) {
                unset($updateData['whatsapp_access_token']);
            } else {
                unset($updateData['whatsapp_access_token']);
            }

            $credentials->update($updateData);
        } else {
            $data = array_merge($validated, ['owner_id' => $ownerId]);
            ChannelCredential::create($data);
        }

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

        return response()->json($result);
    }

    public function sendTestMessage(Request $request): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $token = $request->input('telegram_bot_token');

        if (! $token) {
            $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

            if (! $credentials || ! $credentials->telegram_bot_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay un Token de Bot de Telegram configurado.',
                ]);
            }

            $token = $credentials->telegram_bot_token;
        }

        $chatId = $request->input('telegram_chat_id');

        if (! $chatId) {
            $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

            if (! $credentials || ! $credentials->telegram_chat_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se ha vinculado una cuenta de Telegram. Primero abre el chat del bot y presiona "Iniciar" (/start) o usa el widget de inicio de sesión de Telegram.',
                ]);
            }

            $chatId = $credentials->telegram_chat_id;
        }

        $service = new TelegramService;
        $service->forOwner($ownerId);
        $result = $service->sendMessage($chatId, '🤖 Mensaje de prueba desde Aldia');

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
}
