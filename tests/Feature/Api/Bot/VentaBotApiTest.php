<?php

use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function ventaBotHeaders(User $owner, ?int $actingUserId = null): array
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

test('ventas index returns only the authenticated tenant sales', function () {
    Venta::factory()->create(['owner_id' => $this->owner->id, 'numero' => 'FV-A-001', 'total' => 100]);
    Venta::factory()->create(['owner_id' => $this->otherOwner->id, 'numero' => 'FV-B-001', 'total' => 999]);

    $this->getJson('/api/v1/bot/ventas', ventaBotHeaders($this->owner))
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.numero', 'FV-A-001')
        ->assertJsonMissing(['numero' => 'FV-B-001']);
});

test('ventas index supports estado, fecha range, cliente and search filters', function () {
    $cliente = Cliente::factory()->create(['owner_id' => $this->owner->id, 'nombre' => 'Marcela Rojas']);

    Venta::factory()->create(['owner_id' => $this->owner->id, 'estado' => 'pagada', 'fecha' => '2026-07-01', 'cliente_id' => $cliente->id]);
    Venta::factory()->create(['owner_id' => $this->owner->id, 'estado' => 'pendiente', 'fecha' => '2026-08-01']);
    Venta::factory()->create(['owner_id' => $this->owner->id, 'estado' => 'pagada', 'fecha' => '2026-08-15']);

    $byEstado = $this->getJson('/api/v1/bot/ventas?estado=pagada', ventaBotHeaders($this->owner));
    $byEstado->assertOk()->assertJsonPath('data.total', 2);

    $inRange = $this->getJson('/api/v1/bot/ventas?fecha_desde=2026-08-01&fecha_hasta=2026-08-31', ventaBotHeaders($this->owner));
    $inRange->assertOk()->assertJsonPath('data.total', 2);

    $byCliente = $this->getJson("/api/v1/bot/ventas?cliente_id={$cliente->id}", ventaBotHeaders($this->owner));
    $byCliente->assertOk()->assertJsonPath('data.total', 1);

    $bySearch = $this->getJson('/api/v1/bot/ventas?search=Rojas', ventaBotHeaders($this->owner));
    $bySearch->assertOk()->assertJsonPath('data.total', 1);
});

test('ventas show returns the sale detail with items', function () {
    $venta = Venta::factory()->create(['owner_id' => $this->owner->id, 'total' => 119]);
    $producto = Producto::factory()->create(['owner_id' => $this->owner->id, 'categoria_id' => null, 'nombre' => 'Monitor']);
    DetalleVenta::factory()->create([
        'venta_id' => $venta->id,
        'owner_id' => $this->owner->id,
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'precio_unitario' => 100,
        'subtotal' => 100,
    ]);

    $this->getJson("/api/v1/bot/ventas/{$venta->id}", ventaBotHeaders($this->owner))
        ->assertOk()
        ->assertJsonPath('data.id', $venta->id)
        ->assertJsonPath('data.items.0.producto', 'Monitor')
        ->assertJsonPath('data.items.0.cantidad', 1);
});

test('ventas store creates a sale, items and decrements stock', function () {
    $cliente = Cliente::factory()->create(['owner_id' => $this->owner->id, 'email' => null]);
    $producto = Producto::factory()->create(['owner_id' => $this->owner->id, 'categoria_id' => null]);
    $inventario = Inventario::factory()->create([
        'owner_id' => $this->owner->id,
        'producto_id' => $producto->id,
        'almacen_id' => null,
        'cantidad' => 10,
    ]);

    $response = $this->postJson('/api/v1/bot/ventas', [
        'cliente_id' => $cliente->id,
        'fecha' => '2026-08-09',
        'metodo_pago' => 'transferencia',
        'tipo_documento' => 'boleta',
        'detalles' => [
            ['producto_id' => $producto->id, 'cantidad' => 2, 'precio_unitario' => 1000],
        ],
    ], ventaBotHeaders($this->owner, $this->worker->id));

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.fecha', '2026-08-09')
        ->assertJsonPath('data.subtotal', 2000)
        ->assertJsonPath('data.total', 2380)
        ->assertJsonPath('data.items.0.cantidad', 2);

    $venta = Venta::first();
    expect($venta->owner_id)->toBe($this->owner->id);
    expect($venta->user_id)->toBe($this->worker->id);
    expect($venta->numero)->not->toBeNull();

    expect($inventario->fresh()->cantidad)->toBe(8.0);
});

test('ventas store requires at least one item', function () {
    $this->postJson('/api/v1/bot/ventas', ['detalles' => []], ventaBotHeaders($this->owner))
        ->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.detalles.0', 'El campo detalles es obligatorio.');
});

test('ventas store rejects items of products from another tenant', function () {
    $productoAjeno = Producto::factory()->create(['owner_id' => $this->otherOwner->id, 'categoria_id' => null]);

    $this->postJson('/api/v1/bot/ventas', [
        'detalles' => [
            ['producto_id' => $productoAjeno->id, 'cantidad' => 1, 'precio_unitario' => 100],
        ],
    ], ventaBotHeaders($this->owner))
        ->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJson([
            'errors' => [
                'detalles.0.producto_id' => ['El campo detalles.0.producto_id no existe.'],
            ],
        ]);
});

test('ventas store rejects invalid estado and tipo_documento', function () {
    $this->postJson('/api/v1/bot/ventas', [
        'estado' => 'inexistente',
        'tipo_documento' => 'otro',
        'detalles' => [
            ['producto_id' => 1, 'cantidad' => 1, 'precio_unitario' => 100],
        ],
    ], ventaBotHeaders($this->owner))
        ->assertStatus(422)
        ->assertJsonPath('errors.estado.0', 'El campo estado no está en la lista de valores permitidos.')
        ->assertJsonPath('errors.tipo_documento.0', 'El campo tipo documento no está en la lista de valores permitidos.');
});

test('ventas store rejects a duplicate custom numero', function () {
    Venta::factory()->create(['owner_id' => $this->owner->id, 'numero' => 'FV-MANUAL-01']);
    $producto = Producto::factory()->create(['owner_id' => $this->owner->id, 'categoria_id' => null]);

    $this->postJson('/api/v1/bot/ventas', [
        'numero' => 'FV-MANUAL-01',
        'detalles' => [
            ['producto_id' => $producto->id, 'cantidad' => 1, 'precio_unitario' => 100],
        ],
    ], ventaBotHeaders($this->owner))
        ->assertStatus(422)
        ->assertJsonPath('errors.numero.0', 'El campo numero ya ha sido registrado.');
});

test('ventas update changes estado, notas and metodo de pago', function () {
    $venta = Venta::factory()->create(['owner_id' => $this->owner->id, 'estado' => 'pendiente']);

    $this->putJson("/api/v1/bot/ventas/{$venta->id}", [
        'estado' => 'pagada',
        'metodo_pago' => 'transferencia',
        'notas' => 'Pagada por transferencia bancaria',
    ], ventaBotHeaders($this->owner))
        ->assertOk()
        ->assertJsonPath('data.estado', 'pagada')
        ->assertJsonPath('data.metodo_pago', 'transferencia');

    expect($venta->fresh()->estado)->toBe('pagada');
});

test('ventas destroy cancels the sale', function () {
    $venta = Venta::factory()->create(['owner_id' => $this->owner->id, 'estado' => 'pendiente']);

    $this->deleteJson("/api/v1/bot/ventas/{$venta->id}", [], ventaBotHeaders($this->owner))
        ->assertOk()
        ->assertJsonPath('message', 'Venta cancelada.');

    expect($venta->fresh()->estado)->toBe('cancelada');
});

test('ventas endpoints require authentication and reject foreign tenants', function () {
    $this->getJson('/api/v1/bot/ventas')
        ->assertStatus(401);

    $ventaA = Venta::factory()->create(['owner_id' => $this->owner->id]);

    $this->getJson("/api/v1/bot/ventas/{$ventaA->id}", ventaBotHeaders($this->otherOwner))
        ->assertStatus(404);

    $this->putJson("/api/v1/bot/ventas/{$ventaA->id}", ['estado' => 'pagada'], ventaBotHeaders($this->otherOwner))
        ->assertStatus(404);

    $this->deleteJson("/api/v1/bot/ventas/{$ventaA->id}", [], ventaBotHeaders($this->otherOwner))
        ->assertStatus(404);
});
