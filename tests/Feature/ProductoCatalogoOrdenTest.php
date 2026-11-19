<?php

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function crearProductoCatalogo(User $user, string $nombre, array $attributes = []): Producto
{
    return Producto::factory()->create(array_merge([
        'owner_id' => $user->getOwnerId(),
        'user_id' => $user->id,
        'categoria_id' => null,
        'codigo' => 'CAT-'.fake()->unique()->numerify('######'),
        'nombre' => $nombre,
        'activo' => true,
    ], $attributes));
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->syncRoles([]);
    $this->user->givePermissionTo(
        Permission::firstOrCreate(['name' => 'comercial.productos.viewAny']),
    );

    $this->actingAs($this->user);
});

test('ordena el catálogo por precio de venta de menor a mayor y de mayor a menor', function () {
    crearProductoCatalogo($this->user, 'Producto económico', ['precio_venta' => 1000]);
    crearProductoCatalogo($this->user, 'Producto premium', ['precio_venta' => 9000]);
    crearProductoCatalogo($this->user, 'Producto intermedio', ['precio_venta' => 5000]);

    $this->get(route('productos.index', ['sort' => 'precio_venta', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'precio_venta')
            ->where('filters.direction', 'asc')
            ->where('productos.data.0.nombre', 'Producto económico')
            ->where('productos.data.1.nombre', 'Producto intermedio')
            ->where('productos.data.2.nombre', 'Producto premium'));

    $this->get(route('productos.index', ['sort' => 'precio_venta', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.direction', 'desc')
            ->where('productos.data.0.nombre', 'Producto premium')
            ->where('productos.data.1.nombre', 'Producto intermedio')
            ->where('productos.data.2.nombre', 'Producto económico'));
});

test('ordena el catálogo por stock total sumando todos los almacenes', function () {
    $almacenA = Almacen::factory()->create(['user_id' => $this->user->id]);
    $almacenB = Almacen::factory()->create(['user_id' => $this->user->id]);

    $poco = crearProductoCatalogo($this->user, 'Poco stock');
    $mucho = crearProductoCatalogo($this->user, 'Mucho stock');
    $medio = crearProductoCatalogo($this->user, 'Stock medio');

    Inventario::factory()->create([
        'producto_id' => $poco->id,
        'owner_id' => $this->user->getOwnerId(),
        'almacen_id' => $almacenA->id,
        'cantidad' => 3,
    ]);

    Inventario::factory()->create([
        'producto_id' => $medio->id,
        'owner_id' => $this->user->getOwnerId(),
        'almacen_id' => $almacenA->id,
        'cantidad' => 20,
    ]);

    Inventario::factory()->create([
        'producto_id' => $mucho->id,
        'owner_id' => $this->user->getOwnerId(),
        'almacen_id' => $almacenA->id,
        'cantidad' => 30,
    ]);

    Inventario::factory()->create([
        'producto_id' => $mucho->id,
        'owner_id' => $this->user->getOwnerId(),
        'almacen_id' => $almacenB->id,
        'cantidad' => 40,
    ]);

    $this->get(route('productos.index', ['sort' => 'stock', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('productos.data.0.nombre', 'Poco stock')
            ->where('productos.data.1.nombre', 'Stock medio')
            ->where('productos.data.2.nombre', 'Mucho stock'));

    $this->get(route('productos.index', ['sort' => 'stock', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('productos.data.0.nombre', 'Mucho stock')
            ->where('productos.data.1.nombre', 'Stock medio')
            ->where('productos.data.2.nombre', 'Poco stock'));
});

test('ordena el catálogo alfabéticamente por nombre', function () {
    crearProductoCatalogo($this->user, 'Zanahoria');
    crearProductoCatalogo($this->user, 'Aguacate');
    crearProductoCatalogo($this->user, 'Mango');

    $this->get(route('productos.index', ['sort' => 'nombre', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('productos.data.0.nombre', 'Aguacate')
            ->where('productos.data.1.nombre', 'Mango')
            ->where('productos.data.2.nombre', 'Zanahoria'));

    $this->get(route('productos.index', ['sort' => 'nombre', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('productos.data.0.nombre', 'Zanahoria')
            ->where('productos.data.1.nombre', 'Mango')
            ->where('productos.data.2.nombre', 'Aguacate'));
});

test('ignora un criterio de ordenamiento no permitido', function () {
    $antiguo = crearProductoCatalogo($this->user, 'Producto antiguo');
    $antiguo->forceFill(['created_at' => now()->subDay()])->save();

    $reciente = crearProductoCatalogo($this->user, 'Producto reciente');

    $this->get(route('productos.index', ['sort' => 'precio_venta desc; drop table productos', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('productos.data.0.nombre', $reciente->nombre)
            ->where('productos.data.1.nombre', $antiguo->nombre));
});
