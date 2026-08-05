<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ChannelCredential;
use App\Models\TelegramLinkingToken;
use App\Models\WebSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TelegramLinkingController extends Controller
{
    public function generateLink(Request $request): JsonResponse
    {
        try {
            $ownerId = Auth::user()->getOwnerId();

            $type = $request->input('type', 'custom');

            $token = TelegramLinkingToken::generateToken();

            $expiresAt = now()->addMinutes(15);

            TelegramLinkingToken::create([
                'owner_id' => $ownerId,
                'user_id' => Auth::id(),
                'token' => $token,
                'bot_type' => $type,
                'expires_at' => $expiresAt,
            ]);

            if ($type === 'global') {
                $webSettings = WebSetting::getSettings();

                $globalBotUsername = $webSettings->global_telegram_bot_username;

                if (! $globalBotUsername) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No hay un bot oficial configurado en la plataforma.',
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

            $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

            if (! $credentials || ! $credentials->telegram_bot_username) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay un bot de Telegram configurado. Guarda las credenciales primero.',
                ], 422);
            }

            $botUsername = ltrim($credentials->telegram_bot_username, '@');
            $link = "https://t.me/{$botUsername}?start={$token}";

            Log::info('Telegram linking token generated', [
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
                'message' => 'Error al generar el enlace de vinculación.',
            ], 500);
        }
    }
}
