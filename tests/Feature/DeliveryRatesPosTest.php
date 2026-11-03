<?php

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\DeliveryRate;
use App\Models\Entrega;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->syncRoles([]);

    $permissions = [
        'ventas.pos.viewAny',
        'ventas.pos.create',
        'flota.entregas.viewAny',
        'flota.entregas.create',
        'flota.entregas.edit',
        'flota.entregas.delete',
    ];

    foreach ($permissions as $permission) {
        $permissionModel = Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
        $this->user->givePermissionTo($permissionModel);
    }

    $this->createPosSaleData = function (): array {
        $ownerId = $this->user->id;
        $cliente = Cliente::factory()->create([
            'owner_id' => $ownerId,
            'direccion' => 'Av. Central 123',
            'comuna' => 'Providencia',
        ]);
        $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
        $producto = Producto::factory()->create([
            'owner_id' => $ownerId,
            'precio_venta' => 10000,
            'categoria_id' => null,
        ]);

        Inventario::factory()->create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'cantidad' => 100,
            'owner_id' => $ownerId,
        ]);

        return compact('cliente', 'almacen', 'producto');
    };
});

test('entregas permite gestionar tarifas y lista las propias del tenant', function () {
    $ownRate = DeliveryRate::create([
        'owner_id' => $this->user->id,
        'calculation_type' => 'comuna',
        'commune' => 'Providencia',
        'cost' => 2500,
        'is_active' => true,
    ]);
    $otherOwner = User::factory()->create();
    $otherRate = DeliveryRate::create([
        'owner_id' => $otherOwner->id,
        'calculation_type' => 'comuna',
        'commune' => 'Las Condes',
        'cost' => 3500,
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('entregas.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Backend/Entregas/Index')
            ->has('delivery_rates', 1)
            ->where('delivery_rates.0.id', $ownRate->id));

    $this->actingAs($this->user)
        ->get(route('pos.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Backend/Pos/Index')
            ->has('delivery_rates', 1)
            ->where('delivery_rates.0.id', $ownRate->id));

    $this->actingAs($this->user)
        ->put(route('entregas.tarifas.update', $otherRate), [
            'calculation_type' => 'comuna',
            'commune' => 'Otra',
            'cost' => 1000,
            'is_active' => true,
        ])
        ->assertNotFound();

    $this->actingAs($this->user)
        ->post(route('entregas.tarifas.store'), [
            'calculation_type' => 'rango_km',
            'km_from' => 0,
            'km_to' => 5,
            'cost' => 1800,
            'is_active' => true,
        ])
        ->assertRedirect(route('entregas.index'))
        ->assertSessionHasNoErrors();

    $rangeRate = DeliveryRate::where('owner_id', $this->user->id)
        ->where('calculation_type', 'rango_km')
        ->firstOrFail();

    expect((float) $rangeRate->km_from)->toBe(0.0)
        ->and((float) $rangeRate->km_to)->toBe(5.0)
        ->and((float) $rangeRate->cost)->toBe(1800.0);

    $this->actingAs($this->user)
        ->put(route('entregas.tarifas.update', $rangeRate), [
            'calculation_type' => 'valor_fijo_km',
            'cost' => 750,
            'is_active' => false,
        ])
        ->assertRedirect(route('entregas.index'))
        ->assertSessionHasNoErrors();

    expect($rangeRate->refresh()->is_active)->toBeFalse()
        ->and($rangeRate->calculation_type)->toBe('valor_fijo_km')
        ->and($rangeRate->commune)->toBeNull();

    $this->actingAs($this->user)
        ->delete(route('entregas.tarifas.destroy', $rangeRate))
        ->assertRedirect(route('entregas.index'));

    expect(DeliveryRate::whereKey($rangeRate->id)->exists())->toBeFalse();
});

test('POS aplica la tarifa configurada y crea un despacho con sus productos', function () {
    ['cliente' => $cliente, 'almacen' => $almacen, 'producto' => $producto] =
        ($this->createPosSaleData)();
    $rate = DeliveryRate::create([
        'owner_id' => $this->user->id,
        'calculation_type' => 'comuna',
        'commune' => 'Providencia',
        'cost' => 2500,
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 10000],
            ],
            'subtotal' => 1,
            'iva' => 0,
            'total' => 1,
            'incluye_iva' => true,
            'has_delivery' => true,
            'delivery_rate_id' => $rate->id,
            'delivery_cost' => 1,
            'delivery_address' => 'Av. Central 123, Providencia',
            'almacen_id' => $almacen->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::where('owner_id', $this->user->id)->firstOrFail();
    $entrega = Entrega::where('venta_id', $venta->id)->firstOrFail();

    expect($venta->has_delivery)->toBeTrue()
        ->and($venta->delivery_rate_id)->toBe($rate->id)
        ->and((float) $venta->subtotal)->toBe(12500.0)
        ->and((float) $venta->delivery_cost)->toBe(2500.0)
        ->and((float) $venta->iva)->toBe(1996.0)
        ->and((float) $venta->total)->toBe(12500.0)
        ->and($entrega->owner_id)->toBe($this->user->id)
        ->and($entrega->cliente_id)->toBe($cliente->id)
        ->and($entrega->direccion)->toBe('Av. Central 123, Providencia')
        ->and($entrega->estado)->toBe('pendiente')
        ->and($entrega->items()->count())->toBe(1);
});

test('POS calcula una tarifa fija por kilómetro en el servidor', function () {
    ['cliente' => $cliente, 'almacen' => $almacen, 'producto' => $producto] =
        ($this->createPosSaleData)();
    $rate = DeliveryRate::create([
        'owner_id' => $this->user->id,
        'calculation_type' => 'valor_fijo_km',
        'cost' => 750,
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 10000],
            ],
            'subtotal' => 10000,
            'iva' => 0,
            'total' => 10000,
            'incluye_iva' => false,
            'has_delivery' => true,
            'delivery_rate_id' => $rate->id,
            'delivery_cost' => 0,
            'delivery_distance_km' => 3.2,
            'delivery_address' => 'Av. Central 123, Providencia',
            'almacen_id' => $almacen->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::where('owner_id', $this->user->id)->firstOrFail();

    expect((float) $venta->delivery_cost)->toBe(2400.0)
        ->and((float) $venta->delivery_distance_km)->toBe(3.2)
        ->and((float) $venta->total)->toBe(12400.0);
});

test('POS admite monto manual y no acepta tarifas de otro tenant', function () {
    ['cliente' => $cliente, 'almacen' => $almacen, 'producto' => $producto] =
        ($this->createPosSaleData)();

    $this->actingAs($this->user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 10000],
            ],
            'subtotal' => 10000,
            'iva' => 0,
            'total' => 10000,
            'incluye_iva' => false,
            'has_delivery' => true,
            'delivery_cost' => 2750,
            'delivery_distance_km' => 8.5,
            'delivery_address' => 'Av. Central 123, Providencia',
            'almacen_id' => $almacen->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::where('owner_id', $this->user->id)->firstOrFail();

    expect($venta->delivery_rate_id)->toBeNull()
        ->and((float) $venta->delivery_cost)->toBe(2750.0)
        ->and((float) $venta->total)->toBe(12750.0);

    $otherOwner = User::factory()->create();
    $otherRate = DeliveryRate::create([
        'owner_id' => $otherOwner->id,
        'calculation_type' => 'comuna',
        'commune' => 'Las Condes',
        'cost' => 3500,
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 10000],
            ],
            'subtotal' => 10000,
            'iva' => 0,
            'total' => 10000,
            'incluye_iva' => false,
            'has_delivery' => true,
            'delivery_rate_id' => $otherRate->id,
            'delivery_cost' => 1,
            'delivery_address' => 'Av. Central 123, Providencia',
            'almacen_id' => $almacen->id,
        ])
        ->assertSessionHasErrors('delivery_rate_id');

    expect(Venta::where('owner_id', $this->user->id)->count())->toBe(1);
});
