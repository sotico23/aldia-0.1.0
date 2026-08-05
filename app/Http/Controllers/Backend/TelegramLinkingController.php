<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ChannelCredential;
use App\Models\TelegramLinkingToken;
use App\Models\WebSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TelegramLinkingController extends Controller
{
    /**
     * Atiende el enlace de vinculación abierto en el navegador.
     *
     * Siempre retorna un redirect (nunca una página en blanco). Si el token es
     * válido se redirige a canales pidiendo confirmar en Telegram; si no, se
     * informa que el enlace expiró o es inválido.
     */
    public function confirmLink(string $token): RedirectResponse
    {
        $linkingToken = TelegramLinkingToken::whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('token', $token)
            ->first();

        if (! $linkingToken) {
            return redirect()->route('channel-credentials.index')
                ->with('error', 'El enlace de vinculación es inválido o ha expirado.');
        }

        return redirect()->route('channel-credentials.index')
            ->with('success', 'Enlace generado. Por favor confirma enviando el comando /start en Telegram.');
    }

    /**
     * Genera el token y la URL profunda de Telegram (https://t.me/bot?start=token)
     */
    public function generateLink(Request $request): JsonResponse
    {
        try {
            $ownerId = Auth::user()->getOwnerId();

            // Sincroniza 'type' o 'bot_type' enviados por Axios en el frontend
            $type = $request->input('bot_type', $request->input('type', 'global'));

            $token = TelegramLinkingToken::generateToken();
            $expiresAt = now()->addMinutes(15);

            TelegramLinkingToken::create([
                'owner_id' => $ownerId,
                'user_id' => Auth::id(),
                'token' => $token,
                'bot_type' => $type,
                'expires_at' => $expiresAt,
            ]);

            // CASO 1: BOT OFICIAL / GLOBAL
            if ($type === 'global' || $type === 'oficial') {
                $webSettings = WebSetting::getSettings();
                $globalBotUsername = $webSettings->global_telegram_bot_username ?? config('services.telegram.default_bot_username');

                if (! $globalBotUsername) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No hay un bot oficial configurado en la plataforma. Por favor configúralo en Configuración Web.',
                    ], 422);
                }

                $botUsername = ltrim($globalBotUsername, '@');
                $link = "https://t.me/{$botUsername}?start={$token}";

                Log::info('Telegram global linking token generated', [
                    'owner_id' => $ownerId,
                    'token' => $token,
                    'expires_at' => $expiresAt,
                    'bot_username' => $globalBotUsername,
                ]);

                return response()->json([
                    'success' => true,
                    'telegram_url' => $link,
                    'token' => $token,
                    'bot_username' => $globalBotUsername,
                    'bot_type' => 'global',
                ]);
            }

            // CASO 2: BOT PERSONALIZADO (CUSTOM)
            $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

            if (! $credentials || ! $credentials->telegram_bot_username) {
                // Fallback: si no tiene bot custom guardado pero intenta generar, usar el global si está disponible
                $webSettings = WebSetting::getSettings();
                if (! empty($webSettings->global_telegram_bot_username)) {
                    $globalBotUsername = ltrim($webSettings->global_telegram_bot_username, '@');
                    $link = "https://t.me/{$globalBotUsername}?start={$token}";

                    return response()->json([
                        'success' => true,
                        'telegram_url' => $link,
                        'token' => $token,
                        'bot_username' => $webSettings->global_telegram_bot_username,
                        'bot_type' => 'global',
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'No hay un bot de Telegram configurado. Guarda las credenciales del bot primero.',
                ], 422);
            }

            $botUsername = ltrim($credentials->telegram_bot_username, '@');
            $link = "https://t.me/{$botUsername}?start={$token}";

            Log::info('Telegram custom linking token generated', [
                'owner_id' => $ownerId,
                'token' => $token,
                'expires_at' => $expiresAt,
            ]);

            return response()->json([
                'success' => true,
                'telegram_url' => $link,
                'token' => $token,
                'bot_username' => $credentials->telegram_bot_username,
                'bot_type' => 'custom',
            ]);

        } catch (\Exception $e) {
            Log::error('Telegram linking error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al generar el enlace de vinculación: '.$e->getMessage(),
            ], 500);
        }
    }
}
