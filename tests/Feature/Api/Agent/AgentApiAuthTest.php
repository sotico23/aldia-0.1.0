<?php

/**
 * Tests de la capa de autenticacion del API para agentes (Ola 0).
 *
 * Verifica el middleware `agent-api`:
 *  - Personal Access Token de Sanctum (preferido) con abilities.
 *  - Fallback al `users.api_token` legacy (compatibilidad n8n) con wildcard.
 *  - Aislamiento por tenant mediante X-Owner-ID.
 */

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Support\AgentContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

/**
 * Cabeceras minimas para el API de agentes.
 *
 * @param  array<int, string>  $abilities
 */
function agentHeaders(string $token, int $ownerId, array $abilities = [], ?int $actingUserId = null): array
{
    $headers = [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
        'X-Owner-ID' => (string) $ownerId,
    ];

    if ($actingUserId !== null) {
        $headers['X-User-ID'] = (string) $actingUserId;
    }

    return $headers;
}

beforeEach(function () {
    // El primer usuario creado absorbe el rol Super Admin; se crea un dummy
    // para que el owner del tenant no sea un usuario de plataforma.
    User::factory()->create(['email' => 'dummy-agent-setup@test.local']);

    $this->owner = User::factory()->create([
        'business_name' => 'Tenant A',
        'api_token' => Str::random(60),
    ]);

    $this->otherOwner = User::factory()->create([
        'business_name' => 'Tenant B',
        'api_token' => Str::random(60),
    ]);

    $this->worker = User::factory()->create(['creator_id' => $this->owner->id]);

    $this->tokenService = app(ApiTokenService::class);
});

// ============================================================================
// Ausencia / invalidez de la credencial
// ============================================================================

test('las rutas de agente rechazan peticiones sin token', function () {
    $this->getJson('/api/v1/bot/clientes', [
        'Accept' => 'application/json',
        'X-Owner-ID' => (string) $this->owner->id,
    ])
        ->assertStatus(401)
        ->assertJsonPath('status', 'error')
        ->assertJsonStructure(['status', 'message', 'errors']);
});

test('las rutas de agente rechazan un token inexistente', function () {
    $this->getJson('/api/v1/bot/clientes', agentHeaders('token-que-no-existe', $this->owner->id))
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

test('las rutas de agente exigen la cabecera X-Owner-ID numerica', function () {
    $token = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read'])['token'];

    $this->getJson('/api/v1/bot/clientes', [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');

    $this->getJson('/api/v1/bot/clientes', [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
        'X-Owner-ID' => 'no-numerico',
    ])
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

// ============================================================================
// Personal Access Token de Sanctum
// ============================================================================

test('un PAT con ability agent:read accede a las rutas de lectura', function () {
    Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'Cliente de A']);

    $token = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read'])['token'];

    $this->getJson('/api/v1/bot/clientes', agentHeaders($token, $this->owner->id))
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.nombre', 'Cliente de A');
});

test('un PAT con agent:read NO puede escribir', function () {
    $categoria = Categoria::factory()->create(['owner_id' => $this->owner->id, 'tipo' => 'cliente']);

    $token = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read'])['token'];

    $this->postJson('/api/v1/bot/clientes', [
        'nombre' => 'Cliente nuevo',
        'email' => 'nuevo@correo.cl',
        'categoria_id' => $categoria->id,
    ], agentHeaders($token, $this->owner->id))
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

test('un PAT con agent:write puede escribir', function () {
    $categoria = Categoria::factory()->create(['owner_id' => $this->owner->id, 'tipo' => 'cliente']);

    $token = $this->tokenService->issue($this->owner, 'agente-test', ['agent:read', 'agent:write'])['token'];

    $this->postJson('/api/v1/bot/clientes', [
        'nombre' => 'Cliente nuevo',
        'email' => 'nuevo@correo.cl',
        'categoria_id' => $categoria->id,
    ], agentHeaders($token, $this->owner->id))
        ->assertStatus(201)
        ->assertJsonPath('status', 'success');

    expect(Cliente::where('owner_id', $this->owner->id)->count())->toBe(1);
});

test('un PAT sin abilities no accede a las rutas de lectura', function () {
    $token = $this->tokenService->issue($this->owner, 'flutter-test', [])['token'];

    $this->getJson('/api/v1/bot/clientes', agentHeaders($token, $this->owner->id))
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

test('un PAT expirado es rechazado', function () {
    $token = $this->tokenService->issue(
        $this->owner,
        'flutter-test',
        ['agent:read'],
        now()->subMinute()
    )['token'];

    $this->getJson('/api/v1/bot/clientes', agentHeaders($token, $this->owner->id))
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

test('un PAT de un usuario desactivado es rechazado', function () {
    $token = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read'])['token'];

    // `is_active` no es mass-assignable: se fuerza el valor.
    $this->owner->forceFill(['is_active' => false])->save();

    $this->getJson('/api/v1/bot/clientes', agentHeaders($token, $this->owner->id))
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

test('un PAT de otro tenant no puede operar sobre el tenant indicado', function () {
    $workerExterno = User::factory()->create(['creator_id' => $this->otherOwner->id]);

    $token = $this->tokenService->issue($workerExterno, 'ajeno-test', ['agent:read'])['token'];

    $this->getJson('/api/v1/bot/clientes', agentHeaders($token, $this->owner->id))
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

test('el PAT queda registrado con su nombre y sus abilities', function () {
    $issued = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read', 'agent:write']);

    $pat = PersonalAccessToken::find($issued['id']);

    expect($pat)->not->toBeNull();
    expect($pat->name)->toBe('flutter-test');
    expect($pat->abilities)->toContain('agent:read');
    expect($pat->abilities)->toContain('agent:write');
    expect($pat->tokenable_id)->toBe($this->owner->id);
});

test('X-User-ID permite acting-as de un usuario del propio tenant', function () {
    $token = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read'])['token'];

    $response = $this->getJson(
        '/api/v1/bot/clientes',
        agentHeaders($token, $this->owner->id, [], $this->worker->id)
    );

    $response->assertOk();

    $context = request()->attributes->get('agent_context');

    expect($context)->toBeInstanceOf(AgentContext::class);
    expect($context->ownerId)->toBe($this->owner->id);
    expect($context->actingUserId)->toBe($this->worker->id);
});

test('X-User-ID de otro tenant es rechazado', function () {
    $otroUsuario = User::factory()->create(['creator_id' => $this->otherOwner->id]);

    $token = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read'])['token'];

    $this->getJson(
        '/api/v1/bot/clientes',
        agentHeaders($token, $this->owner->id, [], $otroUsuario->id)
    )
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

test('el token emitido se puede revocar y deja de dar acceso', function () {
    $issued = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read']);

    $this->getJson('/api/v1/bot/clientes', agentHeaders($issued['token'], $this->owner->id))
        ->assertOk();

    $this->tokenService->revoke(PersonalAccessToken::find($issued['id']));

    $this->getJson('/api/v1/bot/clientes', agentHeaders($issued['token'], $this->owner->id))
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

test('rotar un token emite uno nuevo y revoca el anterior', function () {
    $issued = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read']);

    $rotated = $this->tokenService->rotate(PersonalAccessToken::find($issued['id']));

    expect($rotated['token'])->not->toBe($issued['token']);
    expect($rotated['abilities'])->toBe(['agent:read']);
    expect(PersonalAccessToken::find($issued['id']))->toBeNull();

    $this->getJson('/api/v1/bot/clientes', agentHeaders($rotated['token'], $this->owner->id))
        ->assertOk();

    // El token revocado deja de dar acceso.
    $this->getJson('/api/v1/bot/clientes', agentHeaders($issued['token'], $this->owner->id))
        ->assertStatus(401);
});

// ============================================================================
// Fallback legacy (users.api_token) para los workflows de n8n
// ============================================================================

test('el token legacy api_token mantiene el acceso de lectura', function () {
    Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'Cliente de A']);

    $this->getJson('/api/v1/bot/clientes', agentHeaders($this->owner->api_token, $this->owner->id))
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.total', 1);
});

test('el token legacy api_token conserva el acceso de escritura (wildcard)', function () {
    $categoria = Categoria::factory()->create(['owner_id' => $this->owner->id, 'tipo' => 'cliente']);

    $this->postJson('/api/v1/bot/clientes', [
        'nombre' => 'Cliente n8n',
        'email' => 'n8n@correo.cl',
        'categoria_id' => $categoria->id,
    ], agentHeaders($this->owner->api_token, $this->owner->id))
        ->assertStatus(201)
        ->assertJsonPath('status', 'success');
});

test('el token legacy respeta el aislamiento por tenant', function () {
    $this->getJson('/api/v1/bot/clientes', agentHeaders($this->owner->api_token, $this->otherOwner->id))
        ->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

// ============================================================================
// Endpoints transversales consumibles por el cliente
// ============================================================================

test('GET /api/user responde con el usuario del PAT', function () {
    $token = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read'])['token'];

    $this->getJson('/api/user', [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertOk()
        ->assertJsonPath('id', $this->owner->id)
        ->assertJsonPath('email', $this->owner->email);
});

test('la busqueda global por API queda acotada al tenant', function () {
    Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'Buscable de A']);
    Cliente::factory()->create(['owner_id' => $this->otherOwner->id, 'nombre' => 'Buscable de B']);

    $token = $this->tokenService->issue($this->owner, 'flutter-test', ['agent:read'])['token'];

    $response = $this->getJson(
        '/api/v1/global-search?q=Buscable',
        agentHeaders($token, $this->owner->id)
    );

    $response->assertOk();

    $names = collect($response->json())->pluck('name');

    expect($names)->toContain('Buscable de A');
    expect($names)->not->toContain('Buscable de B');
});

// ============================================================================
// Mapeo de rutas: lectura vs escritura
// ============================================================================

test('las rutas de escritura exigen agent:write y las de lectura agent:read', function () {
    $botRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/bot/'))
        // `openapi` es el contrato publico que consume n8n ("Tools via
        // OpenAPI"), no consume datos del tenant.
        ->reject(fn ($route) => $route->uri() === 'api/v1/bot/openapi');

    $readRoutes = $botRoutes->filter(
        fn ($route) => array_intersect($route->methods(), ['GET', 'HEAD']) !== []
    );

    $writeRoutes = $botRoutes->filter(
        fn ($route) => array_intersect($route->methods(), ['GET', 'HEAD']) === []
    );

    expect($readRoutes)->not->toBeEmpty();
    expect($writeRoutes)->not->toBeEmpty();

    foreach ($readRoutes as $route) {
        expect($route->gatherMiddleware())->toContain('agent-api');
        expect($route->gatherMiddleware())->toContain('ability:agent:read');
    }

    foreach ($writeRoutes as $route) {
        expect($route->gatherMiddleware())->toContain('agent-api');
        expect($route->gatherMiddleware())->toContain('ability:agent:write');
    }
});
