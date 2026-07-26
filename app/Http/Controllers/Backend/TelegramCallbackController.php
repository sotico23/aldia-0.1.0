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

        unset($authData['hash']);

        $botToken = config('services.telegram.bot_token');

        if (! $botToken) {
            $ownerId = Auth::user()->getOwnerId();
            $credentials = ChannelCredential::where('owner_id', $ownerId)->first();
            $botToken = $credentials?->telegram_bot_token;
        }

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

        $ownerId = Auth::user()->getOwnerId();
        $credentials = ChannelCredential::where('owner_id', $ownerId)->first();

        if ($credentials) {
            $credentials->update([
                'telegram_chat_id' => (string) $telegramUserId,
            ]);
        }

        return Redirect::to('/canales')
            ->with('success', '¡Cuenta de Telegram vinculada exitosamente! Ya puedes enviar mensajes de prueba.');
    }
}
