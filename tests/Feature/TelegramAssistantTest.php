<?php

use App\Models\ChannelCredential;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\TelegramConversation;
use App\Models\User;
use App\Models\Venta;
use App\Services\TelegramAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    config([
        'services.llm.enabled' => true,
        'services.llm.endpoint' => 'https://llm.example.com/v1/chat/completions',
        'services.llm.api_key' => 'test-key',
        'services.llm.model' => 'gpt-4o-mini',
    ]);
});

test('assistant stores conversation, calls LLM with persona and replies via Telegram', function () {
    Http::fake([
        'https://llm.example.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hola, en qué te ayudo']]],
        ], 200),
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $result = app(TelegramAssistantService::class)
        ->handleMessage($this->user->getOwnerId(), 'test:token', '123456', '¿Cómo van mis ventas?');

    expect($result['success'])->toBeTrue();
    expect($result['reply'])->toBe('Hola, en qué te ayudo');

    expect(TelegramConversation::count())->toBe(2);
    expect(TelegramConversation::where('role', 'user')->first()->content)->toBe('¿Cómo van mis ventas?');
    expect(TelegramConversation::where('role', 'assistant')->first()->content)->toBe('Hola, en qué te ayudo');

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://llm.example.com/v1/chat/completions') {
            return false;
        }

        $body = $request->data();
        $messages = $body['messages'];

        return $request->url() === 'https://llm.example.com/v1/chat/completions'
            && $request->hasHeader('Authorization')
            && $body['model'] === 'gpt-4o-mini'
            && $messages[0]['role'] === 'system'
            && str_contains($messages[0]['content'], 'asistente virtual oficial de "Al Día"')
            && $messages[1]['role'] === 'system'
            && str_contains($messages[1]['content'], 'Contexto actual del negocio')
            && $messages[2]['role'] === 'user'
            && $messages[2]['content'] === '¿Cómo van mis ventas?';
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org/bottest:token/sendMessage')
            && $request['chat_id'] === '123456'
            && $request['text'] === 'Hola, en qué te ayudo';
    });
});

test('assistant includes business context from sales and low stock in LLM request', function () {
    $ownerId = $this->user->getOwnerId();

    Venta::create(['owner_id' => $ownerId, 'fecha' => now(), 'total' => 100]);
    Venta::create(['owner_id' => $ownerId, 'fecha' => now()->subDays(1), 'total' => 250]);

    $producto = Producto::create([
        'owner_id' => $ownerId,
        'codigo' => 'P-001',
        'nombre' => 'Harina',
        'peso_base' => 1,
        'precio_venta' => 1000,
        'stock_minimo' => 5,
    ]);

    Inventario::create([
        'owner_id' => $ownerId,
        'producto_id' => $producto->id,
        'cantidad' => 2,
        'cantidad_minima' => 5,
    ]);

    Http::fake([
        'https://llm.example.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ], 200),
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    app(TelegramAssistantService::class)->handleMessage($ownerId, 'test:token', '123456', 'Hola');

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://llm.example.com/v1/chat/completions') {
            return false;
        }

        $context = $request->data()['messages'][1]['content'];

        return str_contains($context, 'Ventas de hoy')
            && str_contains($context, '100')
            && str_contains($context, 'Ventas de este mes')
            && str_contains($context, '350')
            && str_contains($context, 'Harina')
            && str_contains($context, 'stock bajo');
    });
});

test('assistant sends polite fallback when LLM request fails', function () {
    Http::fake([
        'https://llm.example.com/*' => Http::response(null, 500),
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $result = app(TelegramAssistantService::class)
        ->handleMessage($this->user->getOwnerId(), 'test:token', '123456', 'Hola');

    expect($result['success'])->toBeFalse();
    expect($result['reply'])->toContain('no puedo consultar');

    expect(TelegramConversation::where('role', 'user')->count())->toBe(1);
    expect(TelegramConversation::where('role', 'assistant')->count())->toBe(0);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org')
            && str_contains($request['text'], '😔');
    });
});

test('assistant keeps conversation memory across messages', function () {
    Http::fake([
        'https://llm.example.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Respuesta']]],
        ], 200),
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $service = app(TelegramAssistantService::class);
    $service->handleMessage($this->user->getOwnerId(), 'test:token', '123456', 'Primer mensaje');
    $service->handleMessage($this->user->getOwnerId(), 'test:token', '123456', 'Segundo mensaje');

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://llm.example.com/v1/chat/completions') {
            return false;
        }

        $messages = collect($request->data()['messages']);

        return $messages->contains(fn ($m) => $m['role'] === 'user' && $m['content'] === 'Primer mensaje')
            && $messages->last() === ['role' => 'user', 'content' => 'Segundo mensaje'];
    });
});

test('webhook routes linked chat message to LLM assistant when enabled and skips n8n', function () {
    config([
        'services.n8n.telegram_proxy_url' => 'https://n8n.redcliente.cl/webhook/telegram-proxy',
        'services.llm.enabled' => true,
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '321654',
    ]);

    Http::fake([
        'https://llm.example.com/*' => Http::response([
            'choices' => [['message' => ['content' => '¡Hola! ¿En qué te ayudo?']]],
        ], 200),
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
        'n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 321654],
            'text' => '¿Cuánto vendí hoy?',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok', 'handled_by' => 'llm']);

    Http::assertSent(fn ($request) => $request->url() === 'https://llm.example.com/v1/chat/completions');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'n8n.redcliente.cl'));

    expect(TelegramConversation::where('role', 'user')->first()->content)->toBe('¿Cuánto vendí hoy?');
});

test('webhook falls back to n8n when LLM assistant fails', function () {
    config([
        'services.n8n.telegram_proxy_url' => 'https://n8n.redcliente.cl/webhook/telegram-proxy',
        'services.llm.enabled' => true,
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '321654',
    ]);

    Http::fake([
        'https://llm.example.com/*' => Http::response(null, 500),
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
        'n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 321654],
            'text' => '¿Cuánto vendí hoy?',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    Http::assertSent(fn ($request) => $request->url() === 'https://llm.example.com/v1/chat/completions');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'n8n.redcliente.cl'));

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org') && str_contains($request['text'], '😔');
    });
});

test('webhook routes linked chat message to n8n when llm is disabled', function () {
    config([
        'services.n8n.telegram_proxy_url' => 'https://n8n.redcliente.cl/webhook/telegram-proxy',
        'services.llm.enabled' => false,
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
        'telegram_bot_username' => 'test_bot',
        'telegram_chat_id' => '321654',
    ]);

    Http::fake([
        'n8n.redcliente.cl/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $response = $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 321654],
            'text' => '¿Cuánto vendí hoy?',
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'n8n.redcliente.cl'));
    Http::assertNotSent(fn ($request) => $request->url() === 'https://llm.example.com/v1/chat/completions');

    $this->assertDatabaseCount('telegram_conversations', 0);
});
