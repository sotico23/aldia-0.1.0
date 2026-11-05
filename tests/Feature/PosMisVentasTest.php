<?php

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Otorga permisos POS al usuario indicado.
 *
 * @param  list<string>  $permisos
 */
function otorgarPermisosMisVentas(User $user, array $permisos = ['ventas.pos.viewAny']): void
{
    foreach ($permisos as $permiso) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permiso]));
    }
}

/**
 * Crea un cajero perteneciente al tenant del owner indicado.
 *
 * @param  array<string, mixed>  $attributes
 */
function crearCajeroMisVentas(User $owner, array $attributes = []): User
{
    $cajero = User::factory()->create([
        'creator_id' => $owner->id,
        'owner_id' => $owner->getOwnerId(),
        ...$attributes,
    ]);

    $cajero->syncRoles([]);

    return $cajero;
}

/**
 * Crea una venta POS pagada para el cajero indicado.
 *
 * @param  array<string, mixed>  $attributes
 */
function crearVentaPosMisVentas(User $cajero, int $ownerId, array $attributes = []): Venta
{
    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);

    return Venta::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $cajero->id,
        'cliente_id' => $cliente->id,
        'es_pos' => true,
        'estado' => 'pagada',
        'fecha' => now()->toDateString(),
        'subtotal' => 10000,
        'iva' => 1900,
        'total' => 11900,
        'metodo_pago' => 'efectivo',
        'tipo_documento' => 'boleta',
        ...$attributes,
    ]);
}

test('un cajero sólo ve sus propias ventas y métricas', function () {
    $owner = User::factory()->create();
    $cajero = crearCajeroMisVentas($owner, ['name' => 'Cajera Uno']);
    $otroCajero = crearCajeroMisVentas($owner, ['name' => 'Cajero Dos']);
    otorgarPermisosMisVentas($cajero);

    $ventaEfectivo = crearVentaPosMisVentas($cajero, $owner->getOwnerId());
    $ventaTarjeta = crearVentaPosMisVentas($cajero, $owner->getOwnerId(), [
        'metodo_pago' => 'tarjeta',
        'subtotal' => 20000,
        'iva' => 3800,
        'total' => 23800,
    ]);
    $ventaAjena = crearVentaPosMisVentas($otroCajero, $owner->getOwnerId(), ['total' => 50000]);

    $this->actingAs($cajero)
        ->get(route('pos.mis-ventas'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Pos/MisVentas')
            ->where('filtros.cajero_id', $cajero->id)
            ->where('puede_ver_todos', false)
            ->where('ventas.data', fn ($data) => $data->pluck('id')->sort()->values()->all()
                === collect([$ventaEfectivo->id, $ventaTarjeta->id])->sort()->values()->all())
            ->where('metricas.dia.cantidad', 2)
            ->where('metricas.dia.total', fn ($total) => (float) $total === 35700.0)
            ->where('metricas.ticket_promedio', fn ($total) => (float) $total === 17850.0)
            ->where('metricas.metodo_predominante.clave', 'tarjeta')
            ->where('metricas.metodo_predominante.porcentaje', fn ($p) => (float) $p === 66.7)
        );

    expect($ventaAjena->id)->not->toBeIn([$ventaEfectivo->id, $ventaTarjeta->id]);
});

test('las métricas y el detalle muestran sólo las comisiones de las ventas vigentes del cajero', function () {
    $owner = User::factory()->create();
    $cajero = crearCajeroMisVentas($owner);
    $otroCajero = crearCajeroMisVentas($owner);
    otorgarPermisosMisVentas($cajero);
    $this->actingAs($cajero);

    $ownerId = $owner->getOwnerId();
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'categoria_id' => null,
    ]);

    $ventaHoy = crearVentaPosMisVentas($cajero, $ownerId);
    $ventaPeriodo = crearVentaPosMisVentas($cajero, $ownerId, [
        'fecha' => now()->subDay()->toDateString(),
    ]);
    $ventaAjena = crearVentaPosMisVentas($otroCajero, $ownerId);
    $ventaAnulada = crearVentaPosMisVentas($cajero, $ownerId, [
        'estado' => 'anulada',
    ]);

    foreach ([
        [$ventaHoy, 150.25],
        [$ventaPeriodo, 200],
        [$ventaAjena, 500],
        [$ventaAnulada, 900],
    ] as [$venta, $comision]) {
        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => 1000,
            'subtotal' => 1000,
            'comision_cajero' => $comision,
        ]);
    }

    $this->get(route('pos.mis-ventas', ['rango' => 'mes']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metricas.ganancia_cajero.dia', 150.25)
            ->where('metricas.ganancia_cajero.periodo', 350.25)
        );

    $this->get(route('pos.mis-ventas.show', $ventaHoy))
        ->assertSuccessful()
        ->assertJsonPath('venta.items.0.comision_cajero', 150.25);
});

test('un supervisor puede consultar todos los cajeros o filtrar por uno', function () {
    $owner = User::factory()->create();
    $supervisor = User::factory()->create([
        'creator_id' => $owner->id,
        'owner_id' => $owner->getOwnerId(),
    ]);
    $supervisor->syncRoles(['Administrador']);
    otorgarPermisosMisVentas($supervisor);

    $cajeroA = crearCajeroMisVentas($owner, ['name' => 'Cajero A']);
    $cajeroB = crearCajeroMisVentas($owner, ['name' => 'Cajero B']);

    $ventaA = crearVentaPosMisVentas($cajeroA, $owner->getOwnerId());
    $ventaB = crearVentaPosMisVentas($cajeroB, $owner->getOwnerId());
    $producto = Producto::factory()->create([
        'owner_id' => $owner->getOwnerId(),
        'categoria_id' => null,
    ]);
    $this->actingAs($supervisor);

    DetalleVenta::create([
        'owner_id' => $owner->getOwnerId(),
        'venta_id' => $ventaA->id,
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'precio_unitario' => 1000,
        'subtotal' => 1000,
        'comision_cajero' => 10,
    ]);
    DetalleVenta::create([
        'owner_id' => $owner->getOwnerId(),
        'venta_id' => $ventaB->id,
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'precio_unitario' => 1000,
        'subtotal' => 1000,
        'comision_cajero' => 20,
    ]);

    $this->actingAs($supervisor)
        ->get(route('pos.mis-ventas', ['cajero_id' => 'all']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('puede_ver_todos', true)
            ->where('filtros.cajero_id', null)
            ->where('metricas.ganancia_cajero.dia', fn ($total) => (float) $total === 30.0)
            ->where('metricas.ganancia_cajero.periodo', fn ($total) => (float) $total === 30.0)
            ->where('ventas.data', fn ($data) => $data->pluck('id')->sort()->values()->all()
                === collect([$ventaA->id, $ventaB->id])->sort()->values()->all())
        );

    $this->actingAs($supervisor)
        ->get(route('pos.mis-ventas', ['cajero_id' => $cajeroB->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.cajero_id', $cajeroB->id)
            ->where('metricas.ganancia_cajero.dia', fn ($total) => (float) $total === 20.0)
            ->where('metricas.ganancia_cajero.periodo', fn ($total) => (float) $total === 20.0)
            ->has('ventas.data', 1)
            ->where('ventas.data.0.id', $ventaB->id)
        );
});

test('el detalle devuelve la venta propia y prohíbe la de otro cajero', function () {
    $owner = User::factory()->create();
    $cajero = crearCajeroMisVentas($owner);
    $otroCajero = crearCajeroMisVentas($owner);
    otorgarPermisosMisVentas($cajero);

    $ventaPropia = crearVentaPosMisVentas($cajero, $owner->getOwnerId());
    $ventaAjena = crearVentaPosMisVentas($otroCajero, $owner->getOwnerId());

    $this->actingAs($cajero)
        ->get(route('pos.mis-ventas.show', $ventaPropia))
        ->assertSuccessful()
        ->assertJsonPath('venta.id', $ventaPropia->id)
        ->assertJsonPath('venta.puede_anular', false);

    $this->actingAs($cajero)
        ->get(route('pos.mis-ventas.show', $ventaAjena))
        ->assertForbidden();
});

test('anular una venta la marca como anulada y restaura el inventario', function () {
    $owner = User::factory()->create();
    $cajero = crearCajeroMisVentas($owner);
    otorgarPermisosMisVentas($cajero, ['ventas.pos.viewAny', 'ventas.pos.delete']);

    $this->actingAs($cajero);

    $ownerId = $owner->getOwnerId();
    $almacen = Almacen::factory()->create(['owner_id' => $ownerId]);
    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'categoria_id' => null,
        'precio_venta' => 10000,
        'envase_retornable' => false,
    ]);
    $inventario = Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'owner_id' => $ownerId,
        'cantidad' => 10,
    ]);

    $venta = crearVentaPosMisVentas($cajero, $ownerId, ['almacen_id' => $almacen->id]);

    DetalleVenta::create([
        'venta_id' => $venta->id,
        'producto_id' => $producto->id,
        'cantidad' => 2,
        'precio_unitario' => 10000,
        'subtotal' => 20000,
    ]);

    $this->post(route('pos.mis-ventas.anular', $venta), ['motivo' => 'Error de digitación'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Venta anulada correctamente.');

    expect($venta->fresh()->estado)->toBe('anulada')
        ->and($venta->fresh()->notas)->toContain('Error de digitación')
        ->and((float) $inventario->fresh()->cantidad)->toBe(12.0);
});

test('anular requiere el permiso de eliminación de ventas POS', function () {
    $owner = User::factory()->create();
    $cajero = crearCajeroMisVentas($owner);
    otorgarPermisosMisVentas($cajero, ['ventas.pos.viewAny']);

    $venta = crearVentaPosMisVentas($cajero, $owner->getOwnerId());

    $this->actingAs($cajero)
        ->post(route('pos.mis-ventas.anular', $venta))
        ->assertForbidden();

    expect($venta->fresh()->estado)->toBe('pagada');
});

test('un cajero no puede anular la venta de otro cajero', function () {
    $owner = User::factory()->create();
    $cajero = crearCajeroMisVentas($owner);
    $otroCajero = crearCajeroMisVentas($owner);
    otorgarPermisosMisVentas($cajero, ['ventas.pos.viewAny', 'ventas.pos.delete']);

    $ventaAjena = crearVentaPosMisVentas($otroCajero, $owner->getOwnerId());

    $this->actingAs($cajero)
        ->post(route('pos.mis-ventas.anular', $ventaAjena))
        ->assertForbidden();

    expect($ventaAjena->fresh()->estado)->toBe('pagada');
});
