<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ChannelCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class TelegramCallbackController extends Controller
{
    /**
     * Verifica la firma HMAC del widget de Telegram solo con fines de login.
     *
     * Este endpoint NO vincula el canal: la vinculación del chat se completa
     * exclusivamente con el deep link `/start TOKEN` (TelegramWebhookService).
     * Se conserva la validación de la firma para que el botón del widget no
     * produzca efectos silenciosos y para mantener la compatibilidad con
     * clientes existentes.
     */
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

        unset($authData['hash'], $authData['token']);

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

        return Redirect::to('/canales')
            ->with('info', 'Autenticación de Telegram verificada. Para vincular tu chat usa "Abrir Chat en Telegram" y confirma el comando /start TOKEN en el bot.');
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
