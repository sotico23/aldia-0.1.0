<?php

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\ClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function tenantHeaders(User $owner, ?int $actingUserId = null): array
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
    $this->owner = User::factory()->create(['api_token' => Str::random(60)]);
    $this->otherOwner = User::factory()->create(['api_token' => Str::random(60)]);
    $this->employee = User::factory()->create(['creator_id' => $this->owner->id]);
});

// CRITICAL RULE 1: strict tenant isolation, even against owner-scope bypass roles.

test('tenant B cannot read tenant A clients', function () {
    $clienteA = Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'Cliente Secreto A']);

    $this->getJson("/api/v1/bot/clientes/{$clienteA->id}", tenantHeaders($this->otherOwner))
        ->assertStatus(404)
        ->assertJsonPath('message', 'Cliente no encontrado.');
});

test('tenant B cannot update or delete tenant A clients', function () {
    $clienteA = Cliente::factory()->create(['owner_id' => $this->owner->id, 'activo' => true]);

    $this->putJson("/api/v1/bot/clientes/{$clienteA->id}", ['nombre' => 'Robado'], tenantHeaders($this->otherOwner))
        ->assertStatus(404);

    $this->deleteJson("/api/v1/bot/clientes/{$clienteA->id}", [], tenantHeaders($this->otherOwner))
        ->assertStatus(404);

    expect($clienteA->fresh()->nombre)->not->toBe('Robado');
    expect($clienteA->fresh()->activo)->toBeTrue();
});

test('a platform (super admin) token resolves context from X-Owner-ID and cannot leak data', function () {
    Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'De A']);
    Cliente::factory()->create(['owner_id' => $this->otherOwner->id, 'nombre' => 'De B']);

    // X-Owner-ID of itself: only tenant A data.
    $this->getJson('/api/v1/bot/clientes', tenantHeaders($this->owner))
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.nombre', 'De A');

    // X-Owner-ID pointing to tenant B: only tenant B data (context resolved per header).
    $headers = tenantHeaders($this->owner);
    $headers['X-Owner-ID'] = (string) $this->otherOwner->id;

    $this->getJson('/api/v1/bot/clientes', $headers)
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.nombre', 'De B');
});

test('a created client is always assigned to the claimed tenant', function () {
    $this->postJson('/api/v1/bot/clientes', [
        'nombre' => 'Cliente del tenant B',
    ], tenantHeaders($this->otherOwner))
        ->assertStatus(201);

    $cliente = Cliente::first();
    expect($cliente->owner_id)->toBe($this->otherOwner->id);
});

// Regression: ClienteService::buscarClientes must not leak via ungrouped OR clauses.

test('ClienteService::buscarClientes does not leak records across tenants', function () {
    $this->actingAs($this->employee);

    Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'Juan Pérez Soto']);
    Cliente::factory()->create(['owner_id' => $this->otherOwner->id, 'nombre' => 'Juan Pérez Gómez']);

    $resultados = (new ClienteService)->buscarClientes('Juan');

    expect($resultados)->toHaveCount(1);
    expect($resultados->first()->owner_id)->toBe($this->owner->id);
});

// CRITICAL RULE 1 (Ventas): tenant isolation over the sales module.

test('tenant B cannot read, update or cancel tenant A sales', function () {
    $ventaA = Venta::factory()->create(['owner_id' => $this->owner->id, 'estado' => 'pendiente']);

    $this->getJson("/api/v1/bot/ventas/{$ventaA->id}", tenantHeaders($this->otherOwner))
        ->assertStatus(404)
        ->assertJsonPath('message', 'Venta no encontrada.');

    $this->putJson("/api/v1/bot/ventas/{$ventaA->id}", ['estado' => 'pagada'], tenantHeaders($this->otherOwner))
        ->assertStatus(404);

    $this->deleteJson("/api/v1/bot/ventas/{$ventaA->id}", [], tenantHeaders($this->otherOwner))
        ->assertStatus(404);

    expect($ventaA->fresh()->estado)->toBe('pendiente');
});

test('a sale created via the bot is always assigned to the claimed tenant', function () {
    $producto = Producto::factory()->create(['owner_id' => $this->otherOwner->id, 'categoria_id' => null]);

    $this->postJson('/api/v1/bot/ventas', [
        'detalles' => [
            ['producto_id' => $producto->id, 'cantidad' => 1, 'precio_unitario' => 500],
        ],
    ], tenantHeaders($this->otherOwner))
        ->assertStatus(201);

    $venta = Venta::first();
    expect($venta->owner_id)->toBe($this->otherOwner->id);
});
