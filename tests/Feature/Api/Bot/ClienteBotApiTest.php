<?php

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function botAuthHeaders(User $owner, ?int $actingUserId = null): array
{
    $headers = [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$owner->api_token,
        'X-Owner-ID' => (string) $owner->id,
    ];

    if ($actingUserId !== null) {
        $headers['X-User-ID'] = (string) $actingUserId;
    }

    return $headers;
}

beforeEach(function () {
    $this->owner = User::factory()->create([
        'business_name' => 'Tenant A',
        'api_token' => Str::random(60),
    ]);

    $this->otherOwner = User::factory()->create([
        'business_name' => 'Tenant B',
        'api_token' => Str::random(60),
    ]);

    $this->worker = User::factory()->create(['creator_id' => $this->owner->id]);
});

test('clientes index returns only the authenticated tenant clients', function () {
    Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'Cliente de A']);
    Cliente::factory()->create(['owner_id' => $this->otherOwner->id, 'nombre' => 'Cliente de B']);

    $response = $this->getJson('/api/v1/bot/clientes', botAuthHeaders($this->owner));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.nombre', 'Cliente de A')
        ->assertJsonMissing(['nombre' => 'Cliente de B']);
});

test('clientes index supports search, pagination and activo filters', function () {
    Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'Juan Pérez', 'activo' => true]);
    Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'María Pérez', 'activo' => false]);
    Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'Carlos Soto', 'activo' => true]);

    $search = $this->getJson('/api/v1/bot/clientes?search=Pérez&limit=10', botAuthHeaders($this->owner));
    $search->assertOk()->assertJsonPath('data.total', 2);

    $activos = $this->getJson('/api/v1/bot/clientes?activo=1&limit=1&offset=0', botAuthHeaders($this->owner));
    $activos->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonCount(1, 'data.items');

    $limit = $this->getJson('/api/v1/bot/clientes?limit=2', botAuthHeaders($this->owner));
    $limit->assertOk()->assertJsonCount(2, 'data.items');
});

test('clientes show returns the client details', function () {
    $cliente = Cliente::factory()->create(['owner_id' => $this->owner->id]);

    $this->getJson("/api/v1/bot/clientes/{$cliente->id}", botAuthHeaders($this->owner))
        ->assertOk()
        ->assertJsonPath('data.id', $cliente->id)
        ->assertJsonPath('data.nombre', $cliente->nombre);
});

test('clientes store creates a client assigned to the tenant', function () {
    $categoria = Categoria::factory()->create(['owner_id' => $this->owner->id, 'tipo' => 'cliente']);

    $response = $this->postJson('/api/v1/bot/clientes', [
        'nombre' => 'Nuevo Cliente',
        'email' => 'nuevo@correo.cl',
        'categoria_id' => $categoria->id,
        'activo' => true,
    ], botAuthHeaders($this->owner, $this->worker->id));

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.nombre', 'Nuevo Cliente');

    $cliente = Cliente::first();
    expect($cliente->owner_id)->toBe($this->owner->id);
    expect($cliente->user_id)->toBe($this->worker->id);
});

test('clientes store rejects invalid payloads', function () {
    $response = $this->postJson('/api/v1/bot/clientes', [
        'nombre' => '',
        'email' => 'no-es-un-email',
    ], botAuthHeaders($this->owner));

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.nombre.0', 'El campo nombre es obligatorio.')
        ->assertJsonPath('errors.email.0', 'El campo correo electrónico no es un correo válido.');
});

test('clientes store rejects duplicate email (global unique constraint)', function () {
    Cliente::factory()->create(['owner_id' => $this->owner->id, 'email' => 'duplicado@correo.cl']);

    // Same tenant: rejected by validation.
    $this->postJson('/api/v1/bot/clientes', [
        'nombre' => 'Duplicado A',
        'email' => 'duplicado@correo.cl',
    ], botAuthHeaders($this->owner))
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'El campo correo electrónico ya ha sido registrado.');

    // Other tenant: also rejected (the clientes.email column is globally unique).
    $this->postJson('/api/v1/bot/clientes', [
        'nombre' => 'Duplicado B',
        'email' => 'duplicado@correo.cl',
    ], botAuthHeaders($this->otherOwner))
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'El campo correo electrónico ya ha sido registrado.');
});

test('clientes update modifies the client', function () {
    $cliente = Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'Antes']);

    $this->putJson("/api/v1/bot/clientes/{$cliente->id}", ['nombre' => 'Después'], botAuthHeaders($this->owner))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Después');

    expect($cliente->fresh()->nombre)->toBe('Después');
});

test('clientes update ignores its own unique fields', function () {
    $cliente = Cliente::factory()->create(['owner_id' => $this->owner->id, 'email' => 'mismo@correo.cl']);

    $this->putJson("/api/v1/bot/clientes/{$cliente->id}", ['email' => 'mismo@correo.cl'], botAuthHeaders($this->owner))
        ->assertOk();
});

test('clientes destroy performs a soft delete via activo=false', function () {
    $cliente = Cliente::factory()->create(['owner_id' => $this->owner->id, 'activo' => true]);

    $this->deleteJson("/api/v1/bot/clientes/{$cliente->id}", [], botAuthHeaders($this->owner))
        ->assertOk()
        ->assertJsonPath('message', 'Cliente desactivado.');

    expect($cliente->fresh()->activo)->toBeFalse();
    expect(Cliente::find($cliente->id))->not->toBeNull();
});

test('clientes endpoints reject requests without a token', function () {
    $this->getJson('/api/v1/bot/clientes', ['X-Owner-ID' => (string) $this->owner->id])
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

test('clientes endpoints reject requests with an invalid token', function () {
    $this->getJson('/api/v1/bot/clientes', [
        'Authorization' => 'Bearer token-invalido',
        'X-Owner-ID' => (string) $this->owner->id,
    ])->assertStatus(401);
});

test('clientes endpoints require the X-Owner-ID header', function () {
    $this->getJson('/api/v1/bot/clientes', [
        'Authorization' => 'Bearer '.$this->owner->api_token,
    ])->assertStatus(401);
});

test('clientes endpoints reject a token whose tenant does not match X-Owner-ID', function () {
    $headers = botAuthHeaders($this->otherOwner);
    $headers['X-Owner-ID'] = (string) $this->owner->id;

    $this->getJson('/api/v1/bot/clientes', $headers)
        ->assertStatus(401);
});

test('clientes endpoints reject an X-User-ID from another tenant', function () {
    $foreign = User::factory()->create(['creator_id' => $this->otherOwner->id]);

    $this->getJson('/api/v1/bot/clientes', botAuthHeaders($this->owner, $foreign->id))
        ->assertStatus(401);
});

test('clientes endpoints accept a valid X-User-ID belonging to the tenant', function () {
    $this->getJson('/api/v1/bot/clientes', botAuthHeaders($this->owner, $this->worker->id))
        ->assertOk();
});

test('clientes endpoints reject requests from a deactivated tenant', function () {
    $this->owner->forceFill(['is_active' => false])->save();

    $this->getJson('/api/v1/bot/clientes', botAuthHeaders($this->owner))
        ->assertStatus(403);
});

test('clientes routes are rate limited and protected by the bot middleware', function () {
    $route = Route::getRoutes()->match(request()->create('/api/v1/bot/clientes', 'GET'));

    expect($route->middleware())->toContain('bot-api')
        ->and($route->middleware())->toContain('throttle:bot');
});

test('openapi document exposes the clientes contract', function () {
    $this->getJson('/api/v1/bot/openapi')
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('info.title', 'Al Día · Bot API')
        ->assertJsonStructure([
            'paths' => [
                '/clientes' => ['get', 'post'],
                '/clientes/{cliente}' => ['get', 'put', 'delete'],
            ],
        ]);
});
