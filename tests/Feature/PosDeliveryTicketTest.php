<?php

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\DeliveryRate;
use App\Models\DetalleVenta;
use App\Models\Empleado;
use App\Models\Entrega;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Otorga los permisos mínimos del POS al usuario indicado.
 *
 * @param  list<string>  $permisos
 */
function otorgarPermisosPosDelivery(User $user, array $permisos = ['ventas.pos.viewAny', 'ventas.pos.create']): void
{
    foreach ($permisos as $permiso) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permiso]));
    }
}

/**
 * Crea los registros base para una venta POS.
 *
 * @return array{0: Almacen, 1: Cliente, 2: Producto}
 */
function crearContextoPosDelivery(User $user): array
{
    $ownerId = $user->getOwnerId();

    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $cliente = Cliente::factory()->create([
        'owner_id' => $ownerId,
        'nombre' => 'Cliente Base',
        'telefono' => '+56 9 1111 1111',
        'comuna' => 'Santiago',
    ]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'categoria_id' => null,
        'precio_venta' => 10000,
        'precio_compra' => 3000,
    ]);

    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'owner_id' => $ownerId,
        'cantidad' => 100,
    ]);

    return [$almacen, $cliente, $producto];
}

/**
 * Crea un empleado activo dentro del tenant.
 *
 * @param  array<string, mixed>  $attributes
 */
function crearEmpleadoPosDelivery(User $owner, array $attributes = []): Empleado
{
    return Empleado::factory()->create([
        'owner_id' => $owner->getOwnerId(),
        'user_id' => $owner->id,
        'creator_id' => $owner->id,
        'estado' => 'activo',
        ...$attributes,
    ]);
}

/**
 * Construye el payload de venta POS.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function payloadVentaPosDelivery(Almacen $almacen, Cliente $cliente, Producto $producto, Empleado $empleado, array $overrides = []): array
{
    return [
        'cliente_id' => $cliente->id,
        'empleado_id' => $empleado->id,
        'metodo_pago' => 'efectivo',
        'tipo_documento' => 'boleta',
        'items' => [
            [
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'precio' => $producto->precio_venta,
            ],
        ],
        'subtotal' => $producto->precio_venta,
        'iva' => 1900,
        'total' => $producto->precio_venta + 1900,
        'almacen_id' => $almacen->id,
        ...$overrides,
    ];
}

test('el despacho exige nombre, teléfono y dirección del receptor', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);
    otorgarPermisosPosDelivery($user);

    [$almacen, $cliente, $producto] = crearContextoPosDelivery($user);
    $empleado = crearEmpleadoPosDelivery($user);
    $rate = DeliveryRate::create([
        'owner_id' => $user->getOwnerId(),
        'calculation_type' => 'comuna',
        'commune' => 'Providencia',
        'cost' => 5000,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('pos.store'), payloadVentaPosDelivery($almacen, $cliente, $producto, $empleado, [
            'has_delivery' => true,
            'delivery_rate_id' => $rate->id,
            'delivery_cost' => 5000,
        ]))
        ->assertSessionHasErrors([
            'delivery_recipient_name',
            'delivery_phone',
            'delivery_address',
        ]);

    expect(Venta::count())->toBe(0);
});

test('pos store persiste los datos de despacho en venta y entrega', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);
    otorgarPermisosPosDelivery($user);

    [$almacen, $cliente, $producto] = crearContextoPosDelivery($user);
    $empleado = crearEmpleadoPosDelivery($user);
    $rate = DeliveryRate::create([
        'owner_id' => $user->getOwnerId(),
        'calculation_type' => 'comuna',
        'commune' => 'Providencia',
        'cost' => 5000,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('pos.store'), payloadVentaPosDelivery($almacen, $cliente, $producto, $empleado, [
            'has_delivery' => true,
            'delivery_rate_id' => $rate->id,
            'delivery_cost' => 5000,
            'delivery_recipient_name' => 'María Destino',
            'delivery_phone' => '+56 9 8765 4321',
            'delivery_address' => 'Av. Siempre Viva 742, Depto 3B',
            'delivery_commune' => 'Providencia',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::latest('id')->firstOrFail();
    $entrega = $venta->entrega;

    expect($venta->has_delivery)->toBeTrue()
        ->and($venta->delivery_recipient_name)->toBe('María Destino')
        ->and($venta->delivery_phone)->toBe('+56 9 8765 4321')
        ->and($venta->delivery_address)->toBe('Av. Siempre Viva 742, Depto 3B')
        ->and($venta->delivery_commune)->toBe('Providencia')
        ->and($entrega)->not->toBeNull()
        ->and($entrega->cliente)->toBe('María Destino')
        ->and($entrega->telefono)->toBe('+56 9 8765 4321')
        ->and($entrega->direccion)->toBe('Av. Siempre Viva 742, Depto 3B')
        ->and($entrega->comuna)->toBe('Providencia');
});

test('un cajero no supervisor no puede registrar la venta a nombre de otro empleado', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);
    otorgarPermisosPosDelivery($user);

    [$almacen, $cliente, $producto] = crearContextoPosDelivery($user);
    $empleadoPropio = crearEmpleadoPosDelivery($user);
    $otroEmpleado = crearEmpleadoPosDelivery($user, [
        'user_id' => User::factory()->create()->id,
        'nombre' => 'Otro',
        'apellido' => 'Cajero',
    ]);

    $this->actingAs($user)
        ->post(route('pos.store'), payloadVentaPosDelivery($almacen, $cliente, $producto, $otroEmpleado))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $venta = Venta::latest('id')->firstOrFail();

    expect($venta->empleado_id)->toBe($empleadoPropio->id)
        ->and($venta->empleado_id)->not->toBe($otroEmpleado->id);
});

test('un administrador explícito sí puede asignar la venta a otro cajero', function () {
    $owner = User::factory()->create();
    $supervisor = User::factory()->create([
        'creator_id' => $owner->id,
        'owner_id' => $owner->getOwnerId(),
    ]);
    $supervisor->syncRoles(['Administrador']);
    otorgarPermisosPosDelivery($supervisor);

    [$almacen, $cliente, $producto] = crearContextoPosDelivery($supervisor);
    crearEmpleadoPosDelivery($supervisor);
    $otroEmpleado = crearEmpleadoPosDelivery($supervisor, [
        'user_id' => User::factory()->create()->id,
        'nombre' => 'Cajero',
        'apellido' => 'Asignado',
    ]);

    $this->actingAs($supervisor)
        ->post(route('pos.store'), payloadVentaPosDelivery($almacen, $cliente, $producto, $otroEmpleado))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Venta::latest('id')->firstOrFail()->empleado_id)->toBe($otroEmpleado->id);
});

test('el ticket pdf devuelve un stream válido con los datos de despacho', function () {
    $user = User::factory()->create(['name' => 'Cajera Test']);
    $user->syncRoles([]);
    otorgarPermisosPosDelivery($user, ['ventas.pos.viewAny']);

    [$almacen, $cliente, $producto] = crearContextoPosDelivery($user);
    $empleado = crearEmpleadoPosDelivery($user, [
        'nombre' => 'Cajera',
        'apellido' => 'Test',
    ]);

    $venta = Venta::factory()->create([
        'owner_id' => $user->getOwnerId(),
        'user_id' => $user->id,
        'cliente_id' => $cliente->id,
        'almacen_id' => $almacen->id,
        'empleado_id' => $empleado->id,
        'numero' => 'POS-TICKET-001',
        'folio' => null,
        'es_pos' => true,
        'estado' => 'pagada',
        'fecha' => now()->toDateString(),
        'subtotal' => 10000,
        'iva' => 1900,
        'total' => 16900,
        'metodo_pago' => 'efectivo',
        'tipo_documento' => 'boleta',
        'has_delivery' => true,
        'delivery_cost' => 5000,
        'delivery_recipient_name' => 'María Destino',
        'delivery_phone' => '+56 9 8765 4321',
        'delivery_address' => 'Av. Siempre Viva 742',
        'delivery_commune' => 'Providencia',
    ]);

    DetalleVenta::create([
        'venta_id' => $venta->id,
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'precio_unitario' => 10000,
        'subtotal' => 10000,
    ]);

    $conductor = Conductor::factory()->create([
        'owner_id' => $user->getOwnerId(),
        'nombre' => 'Juan Repartidor',
    ]);

    Entrega::create([
        'owner_id' => $user->getOwnerId(),
        'venta_id' => $venta->id,
        'cliente_id' => $cliente->id,
        'cliente' => 'María Destino',
        'telefono' => '+56 9 8765 4321',
        'direccion' => 'Av. Siempre Viva 742',
        'comuna' => 'Providencia',
        'conductor_id' => $conductor->id,
        'fecha_entrega' => today(),
        'estado' => 'pendiente',
    ]);

    $response = $this->actingAs($user)->get(route('pos.ticket', $venta));

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    $contenido = $response->getContent();

    expect($contenido)->toStartWith('%PDF')
        ->and(strlen($contenido))->toBeGreaterThan(1000);

    $html = view('pdf.pos-ticket', [
        'venta' => $venta->fresh([
            'cliente',
            'empleado',
            'almacen',
            'deliveryRate',
            'entrega.conductor',
            'detalleVentas.producto',
            'owner',
        ]),
        'documento' => $venta->numero,
    ])->render();

    expect($html)
        ->toContain('María Destino')
        ->toContain('+56 9 8765 4321')
        ->toContain('Av. Siempre Viva 742')
        ->toContain('Providencia')
        ->toContain('Juan Repartidor')
        ->toContain('Cajera Test')
        ->toContain('POS-TICKET-001');
});
