<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\TelegramConversation;
use App\Models\Venta;
use App\Models\WebSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramAssistantService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Eres el asistente virtual oficial de "Al Día", una plataforma SaaS diseñada para ayudar a los usuarios a gestionar sus negocios y consultar sus ventas de forma rápida y eficiente a través de Telegram.

Tu objetivo principal es asistir al usuario con sus consultas de manera profesional y clara.

REGLAS CRÍTICAS DE OPERACIÓN:
1. Verificación de Estado: El sistema ya ha validado si el usuario está vinculado o no antes de que te llegue el mensaje. Si el usuario recibe este mensaje, significa que su cuenta de Telegram está vinculada correctamente y puedes atender sus consultas con total normalidad.
2. Contexto y Memoria: Utiliza la memoria de la conversación (el historial de mensajes incluido en la solicitud) para mantener el hilo de la charla y recordar interacciones recientes.
3. Uso de Datos: Si en la solicitud se incluye un "Contexto actual del negocio" con datos reales de inventario, ventas o stock provenientes de la base de datos de Al Día, úsalo para enriquecer tus respuestas. Esa información es la única fuente de datos de negocio disponible.
4. Tono: Mantén un tono amigable, servicial, directo y enfocado en la productividad del negocio del usuario. No inventes datos financieros ni de ventas; si no tienes la información exacta en el contexto proporcionado, indícalo educadamente y sugiere consultar la plataforma web.
PROMPT;

    public function handleMessage(int $tenantId, string $botToken, string $chatId, string $text): array
    {
        $history = $this->getHistory($chatId);

        TelegramConversation::create([
            'owner_id' => $tenantId,
            'chat_id' => $chatId,
            'role' => 'user',
            'content' => $text,
        ]);

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'system', 'content' => 'Contexto actual del negocio (datos reales de la base de datos de Al Día, úsalos solo si son relevantes para la consulta): '.$this->buildContext($tenantId)],
        ];

        foreach ($history as $message) {
            $messages[] = ['role' => $message->role, 'content' => $message->content];
        }

        $messages[] = ['role' => 'user', 'content' => $text];

        $reply = $this->askLlm($messages);

        if (! $reply) {
            $reply = '😔 En este momento no puedo consultar tu información. Por favor, intenta nuevamente en unos minutos.';

            $this->sendTelegramMessage($botToken, $chatId, $reply);

            return ['success' => false, 'reply' => $reply];
        }

        TelegramConversation::create([
            'owner_id' => $tenantId,
            'chat_id' => $chatId,
            'role' => 'assistant',
            'content' => $reply,
        ]);

        $this->sendTelegramMessage($botToken, $chatId, $reply);

        return ['success' => true, 'reply' => $reply];
    }

    private function getHistory(string $chatId): array
    {
        return TelegramConversation::where('chat_id', $chatId)
            ->orderByDesc('id')
            ->limit((int) config('services.llm.memory_window', 20))
            ->get()
            ->reverse()
            ->values()
            ->all();
    }

    private function buildContext(int $tenantId): string
    {
        $currency = WebSetting::getSettings()?->currency_symbol ?? '$';
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $salesToday = Venta::where('owner_id', $tenantId)
            ->whereDate('fecha', $today)
            ->get(['total']);
        $salesMonth = Venta::where('owner_id', $tenantId)
            ->whereDate('fecha', '>=', $monthStart)
            ->whereDate('fecha', '<=', $today)
            ->get(['total']);

        $lowStock = Inventario::where('owner_id', $tenantId)
            ->whereColumn('cantidad', '<=', 'cantidad_minima')
            ->with('producto:id,nombre')
            ->limit(5)
            ->get();

        $format = fn (float $amount) => number_format($amount, 0, ',', '.');

        $lines = [
            sprintf('Ventas de hoy (%s): %d venta(s) por %s %s.', $today, $salesToday->count(), $currency, $format((float) $salesToday->sum('total'))),
            sprintf('Ventas de este mes: %s %s.', $currency, $format((float) $salesMonth->sum('total'))),
        ];

        if ($lowStock->isNotEmpty()) {
            $products = $lowStock
                ->map(fn ($item) => sprintf('%s (%s unidades)', $item->producto?->nombre ?? 'Producto', $format((float) $item->cantidad)))
                ->implode('; ');
            $lines[] = 'Productos con stock bajo o agotado: '.$products.'.';
        } else {
            $lines[] = 'No hay productos con stock bajo.';
        }

        return implode(' ', $lines);
    }

    private function askLlm(array $messages): ?string
    {
        $endpoint = config('services.llm.endpoint');

        if (! $endpoint) {
            return null;
        }

        try {
            $request = Http::timeout(30)->connectTimeout(10)->asJson();

            if ($apiKey = config('services.llm.api_key')) {
                $request = $request->withToken($apiKey);
            }

            $response = $request->post($endpoint, [
                'model' => config('services.llm.model', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => 0.4,
                'max_tokens' => (int) config('services.llm.max_tokens', 500),
            ]);

            if ($response->successful()) {
                $content = data_get($response->json(), 'choices.0.message.content');

                if (is_string($content) && trim($content) !== '') {
                    return trim($content);
                }
            }

            Log::warning('TelegramAssistantService: LLM request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('TelegramAssistantService: LLM error', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function sendTelegramMessage(string $botToken, string $chatId, string $text): void
    {
        try {
            Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => false])
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);
        } catch (\Exception $e) {
            Log::error('TelegramAssistantService: error sending Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
