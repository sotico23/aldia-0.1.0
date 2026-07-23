<?php

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ownerId = $this->user->getOwnerId();
    $this->categoria = Categoria::factory()->create(['owner_id' => $this->ownerId]);
    $this->cliente = Cliente::factory()->create([
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
    ]);
    $this->almacen = Almacen::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->ownerId,
    ]);
});

test('venta de envase retornable con stock insuficiente crea la venta exitosamente', function () {
    $envase = Producto::factory()->create([
        'codigo' => 'ENV-001',
        'nombre' => 'Botellon 20L',
        'precio_venta' => 8000,
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
        'envase_retornable' => false,
    ]);

    $recarga = Producto::factory()->create([
        'codigo' => 'REC-001',
        'nombre' => 'Recarga Agua 20L',
        'precio_venta' => 3000,
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
        'envase_retornable' => true,
        'envase_producto_id' => $envase->id,
    ]);

    Inventario::create([
        'producto_id' => $recarga->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 2,
        'cantidad_minima' => 1,
    ]);

    Inventario::create([
        'producto_id' => $envase->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 5,
        'cantidad_minima' => 1,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-ENV-NEG-001',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $recarga->id,
                    'cantidad' => 10,
                    'precio_unitario' => 3000,
                    'cantidad_retornada' => 0,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('ventas', ['numero' => 'FV-ENV-NEG-001']);

    $venta = Venta::where('numero', 'FV-ENV-NEG-001')->first();
    expect($venta)->not->toBeNull();

    $invRecarga = Inventario::where('producto_id', $recarga->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect((float) $invRecarga->cantidad)->toBe(-8.0);

    $invEnvase = Inventario::where('producto_id', $envase->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect((float) $invEnvase->cantidad)->toBe(-5.0);
});

test('venta con retornos parciales de envase genera subtotal correcto', function () {
    $envase = Producto::factory()->create([
        'codigo' => 'ENV-002',
        'nombre' => 'Bidon 10L',
        'precio_venta' => 5000,
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
        'envase_retornable' => false,
    ]);

    $recarga = Producto::factory()->create([
        'codigo' => 'REC-002',
        'nombre' => 'Recarga Agua 10L',
        'precio_venta' => 2000,
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
        'envase_retornable' => true,
        'envase_producto_id' => $envase->id,
    ]);

    Inventario::create([
        'producto_id' => $recarga->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 1,
        'cantidad_minima' => 1,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-ENV-NEG-002',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $recarga->id,
                    'cantidad' => 10,
                    'precio_unitario' => 2000,
                    'cantidad_retornada' => 3,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();

    $venta = Venta::where('numero', 'FV-ENV-NEG-002')->first();
    expect($venta)->not->toBeNull();

    $recargaSubtotal = 10 * 2000;
    $envasesPendientes = 10 - 3;
    $envaseSubtotal = $envasesPendientes * 5000;
    expect((int) $venta->subtotal)->toBe($recargaSubtotal + $envaseSubtotal);

    $invRecarga = Inventario::where('producto_id', $recarga->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect((float) $invRecarga->cantidad)->toBe(-9.0);

    $invEnvase = Inventario::where('producto_id', $envase->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect((float) $invEnvase->cantidad)->toBe(-7.0);
});

test('envase retornable sin stock genera venta e inventario negativos', function () {
    $envase = Producto::factory()->create([
        'codigo' => 'ENV-003',
        'nombre' => 'Cilindro Gas',
        'precio_venta' => 15000,
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
        'envase_retornable' => false,
    ]);

    $recarga = Producto::factory()->create([
        'codigo' => 'REC-003',
        'nombre' => 'Gas Licuado 15kg',
        'precio_venta' => 12000,
        'owner_id' => $this->ownerId,
        'categoria_id' => $this->categoria->id,
        'envase_retornable' => true,
        'envase_producto_id' => $envase->id,
    ]);

    Inventario::create([
        'producto_id' => $recarga->id,
        'almacen_id' => $this->almacen->id,
        'cantidad' => 0,
        'cantidad_minima' => 5,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.store'), [
            'numero_factura' => 'FV-ENV-NEG-003',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-07-22',
            'estado' => 'pagada',
            'incluye_iva' => false,
            'productos' => [
                [
                    'producto_id' => $recarga->id,
                    'cantidad' => 3,
                    'precio_unitario' => 12000,
                    'cantidad_retornada' => 0,
                ],
            ],
            'almacen_ids' => [$this->almacen->id],
        ]);

    $response->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('ventas', ['numero' => 'FV-ENV-NEG-003']);

    $invRecarga = Inventario::where('producto_id', $recarga->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect((float) $invRecarga->cantidad)->toBe(-3.0);

    $invEnvase = Inventario::where('producto_id', $envase->id)
        ->where('almacen_id', $this->almacen->id)
        ->first();
    expect((float) $invEnvase->cantidad)->toBe(-3.0);
});
