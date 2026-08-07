<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ChannelCredential;
use App\Models\TelegramLinkingToken;
use App\Services\TelegramService;
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
    public function generateLink(Request $request, TelegramService $telegramService): JsonResponse
    {
        try {
            $ownerId = Auth::user()->getOwnerId();

            // Sincroniza 'type' o 'bot_type' enviados por Axios en el frontend
            $type = $request->input('bot_type', $request->input('type', 'global'));

            $result = $telegramService->generateLinkingToken($ownerId, $type, Auth::id());

            if (! ($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Error al generar el enlace de vinculación.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'telegram_url' => $result['telegram_url'],
                'token' => $result['token'],
                'bot_username' => $result['bot_username'],
                'bot_type' => $result['bot_type'],
                'expires_at' => $result['expires_at']?->toIso8601String(),
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

    /**
     * Desvincula la cuenta de Telegram del owner: limpia el chat_id vinculado
     * en ChannelCredential. Los tokens existentes del owner se invalidan para
     * que ningún /start pendiente vuelva a vincular la cuenta.
     */
    public function unlinkTelegram(): JsonResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

        if ($credentials) {
            $credentials->update([
                'telegram_chat_id' => null,
                'telegram_linked_at' => null,
            ]);
        }

        // Invalidar cualquier token activo pendiente del owner.
        $invalidated = TelegramLinkingToken::query()
            ->where('owner_id', $ownerId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()->subSecond()]);

        Log::info('Telegram account unlinked', [
            'owner_id' => $ownerId,
            'invalidated_tokens' => $invalidated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta de Telegram desvinculada correctamente.',
        ]);
    }
}
