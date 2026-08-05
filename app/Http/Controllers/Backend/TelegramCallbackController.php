<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ChannelCredential;
use App\Models\TelegramLinkingToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class TelegramCallbackController extends Controller
{
    public function handle(Request $request): JsonResponse|RedirectResponse
    {
        $authData = $request->all();
        $receivedHash = $authData['hash'] ?? null;

        if (! $receivedHash) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de autenticación de Telegram incompletos.',
            ]);
        }

        // The optional linking token must NEVER participate in Telegram's
        // hash verification: it is not part of the signed auth payload.
        $linkingToken = $this->resolveLinkingToken($authData);

        unset($authData['hash']);
        unset($authData['token']);

        $botToken = $this->resolveBotToken($request);

        if (! $botToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token de bot de Telegram no configurado.',
            ]);
        }

        $dataCheckString = collect($authData)
            ->sortKeys()
            ->map(fn ($value, $key) => "{$key}=".(is_array($value) ? implode(',', array_map('strval', $value)) : strval($value)))
            ->join("\n");

        $computedHash = hash_hmac('sha256', $dataCheckString, $botToken);

        if (! hash_equals($computedHash, $receivedHash)) {
            return response()->json([
                'success' => false,
                'message' => 'Autenticación de Telegram inválida.',
            ]);
        }

        $telegramUserId = $authData['id'];

        DB::transaction(function () use ($telegramUserId, $linkingToken) {
            $ownerId = Auth::user()?->getOwnerId();

            // 1. Persistir el chat_id en las credenciales del canal.
            if ($ownerId) {
                $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

                if ($credentials) {
                    $credentials->update([
                        'telegram_chat_id' => (string) $telegramUserId,
                        'telegram_linked_at' => now(),
                    ]);
                }
            }

            // 2. Marcar el token de vinculación como usado SOLO cuando llega el token
            //    explícito. El widget de login web no debe consumir tokens del flujo
            //    /start, que es quien cierra el ciclo de vinculación del chat.
            if ($linkingToken) {
                $linkingToken->update([
                    'telegram_chat_id' => (string) $telegramUserId,
                    'used_at' => now(),
                ]);
            }
        });

        return Redirect::to('/canales')
            ->with('success', '¡Cuenta de Telegram vinculada exitosamente! Ya puedes enviar mensajes de prueba.');
    }

    /**
     * Resolve the linking token from the request (query param or body), if present.
     */
    private function resolveLinkingToken(array $authData): ?TelegramLinkingToken
    {
        $tokenStr = $authData['token'] ?? null;

        if (! $tokenStr) {
            return null;
        }

        return TelegramLinkingToken::valid()
            ->where('token', $tokenStr)
            ->first();
    }

    /**
     * Resolve the bot token used to verify the Telegram login payload hash.
     */
    private function resolveBotToken(Request $request): ?string
    {
        $botToken = $request->input('bot_token');

        if ($botToken) {
            return $botToken;
        }

        $botToken = config('services.telegram.bot_token');

        if ($botToken) {
            return $botToken;
        }

        $ownerId = Auth::user()?->getOwnerId();

        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

        return $credentials?->telegram_bot_token;
    }
}
