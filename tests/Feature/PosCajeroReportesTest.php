<?php

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function grantPosPermissions(User $user): void
{
    foreach (['ventas.pos.viewAny', 'ventas.pos.create'] as $permiso) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permiso]));
    }
}

function makePosContext(User $user): array
{
    $ownerId = $user->getOwnerId();

    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
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

function makeEmpleado(User $owner, array $attributes = []): Empleado
{
    return Empleado::factory()->create([
        'owner_id' => $owner->getOwnerId(),
        'user_id' => $owner->id,
        'creator_id' => $owner->id,
        'estado' => 'activo',
        ...$attributes,
    ]);
}

function registrarVentaPos(User $user, Almacen $almacen, Cliente $cliente, Producto $producto, Empleado $empleado, float $cantidad = 1): Venta
{
    $subtotal = $producto->precio_venta * $cantidad;
    $iva = round($subtotal * 0.19);

    test()->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'empleado_id' => $empleado->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio' => $producto->precio_venta,
                ],
            ],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $subtotal + $iva,
            'almacen_id' => $almacen->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    return Venta::latest('id')->firstOrFail();
}

test('el pos expone los empleados activos y preselecciona al usuario autenticado', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);
    grantPosPermissions($user);

    $empleadoActual = makeEmpleado($user);
    makeEmpleado($user, [
        'user_id' => User::factory()->create()->id,
        'estado' => 'activo',
    ]);
    makeEmpleado($user, [
        'user_id' => User::factory()->create()->id,
        'estado' => 'inactivo',
    ]);

    $this->actingAs($user)
        ->get(route('pos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Pos/Index')
            ->has('empleados', 2)
            ->where('empleado_id', $empleadoActual->id)
        );
});

test('pos store guarda el cajero y la ganancia potencial por item', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);
    grantPosPermissions($user);

    [$almacen, $cliente, $producto] = makePosContext($user);
    $empleado = makeEmpleado($user);

    $venta = registrarVentaPos($user, $almacen, $cliente, $producto, $empleado, 2);

    expect($venta->empleado_id)->toBe($empleado->id);

    $detalle = $venta->detalleVentas()->firstOrFail();

    expect((float) $detalle->costo_unitario)->toBe(3000.0)
        ->and((float) $detalle->ganancia)->toBe(14000.0)
        ->and((float) $detalle->precio_unitario)->toBe(10000.0)
        ->and((float) $detalle->cantidad)->toBe(2.0);
});

test('pos store rechaza cajeros de otro owner', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);
    grantPosPermissions($user);

    [$almacen, $cliente, $producto] = makePosContext($user);

    $otroOwner = User::factory()->create();
    $empleadoAjeno = makeEmpleado($otroOwner);

    $this->actingAs($user)
        ->post(route('pos.store'), [
            'cliente_id' => $cliente->id,
            'empleado_id' => $empleadoAjeno->id,
            'metodo_pago' => 'efectivo',
            'tipo_documento' => 'boleta',
            'items' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio' => 10000],
            ],
            'subtotal' => 10000,
            'iva' => 1900,
            'total' => 11900,
            'almacen_id' => $almacen->id,
        ])
        ->assertSessionHasErrors('empleado_id');

    expect(Venta::count())->toBe(0);
});

test('reportes filtra ventas y ganancias por cajero', function () {
    $user = User::factory()->create();
    $user->syncRoles(['Administrador']);
    grantPosPermissions($user);
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'ventas.pos-reportes.viewAny']));

    [$almacen, $cliente, $producto] = makePosContext($user);
    $cajeroA = makeEmpleado($user, ['nombre' => 'Ana', 'apellido' => 'Pérez']);
    $cajeroB = makeEmpleado($user, [
        'user_id' => User::factory()->create()->id,
        'nombre' => 'Bruno',
        'apellido' => 'Soto',
    ]);

    registrarVentaPos($user, $almacen, $cliente, $producto, $cajeroA, 2);
    registrarVentaPos($user, $almacen, $cliente, $producto, $cajeroB, 1);

    $response = $this->actingAs($user)
        ->get(route('pos.reportes', [
            'empleado_id' => $cajeroA->id,
            'fecha_desde' => now()->startOfMonth()->toDateString(),
            'fecha_hasta' => now()->toDateString(),
        ]));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Pos/Reportes')
            ->has('ventasPorCajero', 1)
            ->where('ventasPorCajero.0.empleado_id', $cajeroA->id)
            ->where('ventasPorCajero.0.cajero_nombre', 'Ana Pérez')
            ->where('ventasPorCajero.0.total_ventas', 1)
            ->where('ventasPorCajero.0.ganancia_total', 14000)
            ->has('desgloseProductos', 1)
            ->where('desgloseProductos.0.producto_nombre', $producto->nombre)
            ->where('desgloseProductos.0.cantidad', 2)
            ->where('desgloseProductos.0.ganancia', 14000)
            ->where('totales.total_ventas', 1)
            ->where('cajeros.0.id', $cajeroA->id)
        );
});
