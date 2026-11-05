<?php

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Contracts\LoginResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Crea un cajero con el rol Empleado (rol operativo de caja) y los permisos
 * minimos para acceder al panel y al dashboard base.
 */
function crearCajeroPanel(User $owner, array $attributes = []): User
{
    Role::firstOrCreate(['name' => 'Empleado', 'guard_name' => 'web']);

    $cajero = User::factory()->create([
        'creator_id' => $owner->id,
        'has_explicit_role' => true,
        ...$attributes,
    ]);

    $cajero->syncRoles(['Empleado']);

    foreach (['ventas.pos.viewAny', 'ver dashboard'] as $permiso) {
        $cajero->givePermissionTo(
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web'])
        );
    }

    Empleado::factory()->create([
        'user_id' => $cajero->id,
        'owner_id' => $owner->getOwnerId(),
        'estado' => 'activo',
    ]);

    return $cajero->fresh();
}

/**
 * Crea una venta pagada del cajero con un detalle de producto.
 *
 * @param  array<string, mixed>  $attributes
 */
function crearVentaPanel(User $cajero, int $ownerId, Producto $producto, float $cantidad, array $attributes = []): Venta
{
    $cliente = Cliente::factory()->create(['owner_id' => $ownerId]);
    $precio = (float) $producto->precio_venta;
    $subtotal = $precio * $cantidad;

    $venta = Venta::factory()->create([
        'owner_id' => $ownerId,
        'user_id' => $cajero->id,
        'cliente_id' => $cliente->id,
        'es_pos' => true,
        'estado' => 'pagada',
        'fecha' => now()->toDateString(),
        'subtotal' => $subtotal,
        'iva' => 0,
        'total' => $subtotal,
        'metodo_pago' => 'efectivo',
        'tipo_documento' => 'boleta',
        ...$attributes,
    ]);

    $venta->detalleVentas()->create([
        'producto_id' => $producto->id,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio,
        'subtotal' => $subtotal,
        'comision_cajero' => round(
            (float) $producto->comision_cajero_por_unidad * $cantidad,
            2
        ),
    ]);

    return $venta;
}

beforeEach(function () {
    // El primer usuario creado recibe Super Admin automaticamente; se usa un
    // dummy para que los usuarios del escenario conserven su rol explicito.
    User::factory()->create(['email' => 'dummy-cajero-panel@test.local']);
});

test('un cajero es redirigido del dashboard general a su panel de caja', function () {
    $owner = User::factory()->create();
    $cajero = crearCajeroPanel($owner);

    expect($cajero->esCajero())->toBeTrue();

    $this->actingAs($cajero)
        ->get(route('dashboard'))
        ->assertRedirect(route('cajero.dashboard'));
});

test('el login de un cajero lo dirige a mi caja', function () {
    $owner = User::factory()->create();
    $cajero = crearCajeroPanel($owner);

    Auth::login($cajero);

    $request = Request::create('/login', 'POST', [], [], [], [
        'HTTP_X-Inertia' => 'true',
        'HTTP_ACCEPT' => 'text/html, application/xhtml+xml',
    ]);

    $response = app(LoginResponse::class)->toResponse($request);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toEndWith('/mi-caja');
});

test('el panel filtra todas las metricas por el user_id del cajero autenticado', function () {
    $owner = User::factory()->create();
    $ownerId = $owner->getOwnerId();

    $cajero = crearCajeroPanel($owner);
    $otroCajero = crearCajeroPanel($owner, ['name' => 'Cajero Ajeno']);

    $productoA = Producto::factory()->create([
        'owner_id' => $ownerId,
        'nombre' => 'Cafe molido',
        'precio_venta' => 5000,
        'precio_compra' => 2000,
        'comision_cajero_por_unidad' => 250,
        'stock_minimo' => 0,
    ]);

    $productoB = Producto::factory()->create([
        'owner_id' => $ownerId,
        'nombre' => 'Te verde',
        'precio_venta' => 2500,
        'precio_compra' => 1000,
        'comision_cajero_por_unidad' => 100,
        'stock_minimo' => 0,
    ]);

    Inventario::factory()->create([
        'owner_id' => $ownerId,
        'producto_id' => $productoA->id,
        'cantidad' => 100,
    ]);

    $this->actingAs($cajero);

    $ventaHoy = crearVentaPanel($cajero, $ownerId, $productoA, 2);
    crearVentaPanel($cajero, $ownerId, $productoB, 3, [
        'fecha' => now()->subDay()->toDateString(),
    ]);

    // Venta ajena y venta anulada: no deben sumar en ninguna metrica.
    crearVentaPanel($otroCajero, $ownerId, $productoA, 10, ['total' => 50000]);
    crearVentaPanel($cajero, $ownerId, $productoA, 1, [
        'estado' => 'anulada',
        'total' => 99999,
    ]);

    $this->get(route('cajero.dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Pos/CajeroDashboard')
            ->where('cajero.id', $cajero->id)
            ->where('kpis.ventas_hoy.documentos', 1)
            ->where('kpis.ventas_hoy.total', fn ($total) => (float) $total === 10000.0)
            ->where('kpis.ventas_mes.documentos', 2)
            ->where('kpis.ventas_mes.total', fn ($total) => (float) $total === 17500.0)
            ->where('kpis.ganancia_hoy', fn ($total) => (float) $total === 500.0)
            ->where('kpis.ganancia_mes', fn ($total) => (float) $total === 800.0)
            ->where('kpis.ticket_promedio', fn ($total) => (float) $total === 10000.0)
            ->where('kpis.productos_hoy.unidades', fn ($unidades) => (float) $unidades === 2.0)
            ->where('top_productos.0.nombre', 'Te verde')
            ->where('top_productos.0.cantidad', fn ($cantidad) => (float) $cantidad === 3.0)
            ->where('top_productos.1.nombre', 'Cafe molido')
            ->where('ultimas_ventas', fn ($ventas) => count($ventas) === 1
                && (int) $ventas[0]['id'] === $ventaHoy->id)
            ->where('moneda', 'CLP')
            ->has('tips')
        );
});

test('el panel muestra el historial reciente cuando el cajero no vende hoy', function () {
    $owner = User::factory()->create();
    $ownerId = $owner->getOwnerId();
    $cajero = crearCajeroPanel($owner);

    $producto = Producto::factory()->create([
        'owner_id' => $ownerId,
        'precio_venta' => 4000,
        'precio_compra' => 1500,
        'stock_minimo' => 0,
    ]);

    $this->actingAs($cajero);

    $ventaAyer = crearVentaPanel($cajero, $ownerId, $producto, 1, [
        'fecha' => now()->subDay()->toDateString(),
    ]);

    $this->get(route('cajero.dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.ventas_hoy.documentos', 0)
            ->where('ultimas_ventas', fn ($ventas) => count($ventas) === 1
                && (int) $ventas[0]['id'] === $ventaAyer->id)
        );
});

test('un usuario sin rol de caja no es tratado como cajero', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create([
        'creator_id' => $owner->id,
        'has_explicit_role' => true,
    ]);
    $admin->syncRoles(['Administrador']);
    $admin->givePermissionTo(
        Permission::firstOrCreate(['name' => 'ventas.pos.viewAny', 'guard_name' => 'web'])
    );

    expect($admin->esCajero())->toBeFalse();

    $this->actingAs($admin)
        ->get(route('cajero.dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Backend/Pos/CajeroDashboard'));
});

test('un usuario sin permiso de pos no puede acceder al panel de caja', function () {
    $owner = User::factory()->create();
    $usuario = User::factory()->create([
        'creator_id' => $owner->id,
        'has_explicit_role' => true,
    ]);
    $usuario->syncRoles(['Usuario']);
    $usuario->givePermissionTo(
        Permission::firstOrCreate(['name' => 'ver dashboard', 'guard_name' => 'web'])
    );

    $this->actingAs($usuario)
        ->get(route('cajero.dashboard'))
        ->assertForbidden();
});
