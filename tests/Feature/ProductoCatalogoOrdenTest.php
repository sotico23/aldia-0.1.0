<?php

use App\Livewire\Productos\Index;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use Livewire\Livewire;
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

/** @return array<int, string> */
function nombresDelCatalogo($paginator): array
{
    return $paginator->getCollection()->pluck('nombre')->values()->all();
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->syncRoles([]);
    $this->user->givePermissionTo(
        Permission::firstOrCreate(['name' => 'comercial.productos.viewAny']),
    );

    $this->actingAs($this->user);
});

test('la página se abre como Livewire y expulsa a Inertia', function () {
    $this->withoutVite();

    $this->get(route('productos.index'))
        ->assertOk()
        ->assertSeeLivewire(Index::class)
        ->assertSee('livewire.csp', false);

    $this->get(route('productos.index'), ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('productos.index'));
});

test('ordena el catálogo por precio de venta en ambos sentidos', function () {
    crearProductoCatalogo($this->user, 'Producto económico', ['precio_venta' => 1000]);
    crearProductoCatalogo($this->user, 'Producto premium', ['precio_venta' => 9000]);
    crearProductoCatalogo($this->user, 'Producto intermedio', ['precio_venta' => 5000]);

    Livewire::test(Index::class)
        ->set('orden', 'precio_venta')
        ->set('direccion', 'asc')
        ->assertViewHas('productos', fn ($productos): bool => nombresDelCatalogo($productos) === [
            'Producto económico', 'Producto intermedio', 'Producto premium',
        ])
        ->set('direccion', 'desc')
        ->assertViewHas('productos', fn ($productos): bool => nombresDelCatalogo($productos) === [
            'Producto premium', 'Producto intermedio', 'Producto económico',
        ]);
});

test('ordena el catálogo por stock total sumando todos los almacenes', function () {
    $almacenA = Almacen::factory()->create(['user_id' => $this->user->id]);
    $almacenB = Almacen::factory()->create(['user_id' => $this->user->id]);

    $poco = crearProductoCatalogo($this->user, 'Poco stock');
    $mucho = crearProductoCatalogo($this->user, 'Mucho stock');
    $medio = crearProductoCatalogo($this->user, 'Stock medio');

    foreach ([[$poco, $almacenA, 3], [$medio, $almacenA, 20], [$mucho, $almacenA, 30], [$mucho, $almacenB, 40]] as [$producto, $almacen, $cantidad]) {
        Inventario::factory()->create([
            'producto_id' => $producto->id,
            'owner_id' => $this->user->getOwnerId(),
            'almacen_id' => $almacen->id,
            'cantidad' => $cantidad,
        ]);
    }

    Livewire::test(Index::class)
        ->set('orden', 'stock')
        ->set('direccion', 'asc')
        ->assertViewHas('productos', fn ($productos): bool => nombresDelCatalogo($productos) === [
            'Poco stock', 'Stock medio', 'Mucho stock',
        ])
        ->set('direccion', 'desc')
        ->assertViewHas('productos', fn ($productos): bool => nombresDelCatalogo($productos) === [
            'Mucho stock', 'Stock medio', 'Poco stock',
        ]);
});

test('ordena el catálogo alfabéticamente por nombre', function () {
    crearProductoCatalogo($this->user, 'Zanahoria');
    crearProductoCatalogo($this->user, 'Aguacate');
    crearProductoCatalogo($this->user, 'Mango');

    Livewire::test(Index::class)
        ->set('orden', 'nombre')
        ->set('direccion', 'asc')
        ->assertViewHas('productos', fn ($productos): bool => nombresDelCatalogo($productos) === [
            'Aguacate', 'Mango', 'Zanahoria',
        ])
        ->set('direccion', 'desc')
        ->assertViewHas('productos', fn ($productos): bool => nombresDelCatalogo($productos) === [
            'Zanahoria', 'Mango', 'Aguacate',
        ]);
});

test('ignora un criterio de ordenamiento no permitido', function () {
    $antiguo = crearProductoCatalogo($this->user, 'Producto antiguo');
    $antiguo->forceFill(['created_at' => now()->subDay()])->save();
    $reciente = crearProductoCatalogo($this->user, 'Producto reciente');

    Livewire::test(Index::class)
        ->set('orden', 'precio_venta desc; drop table productos')
        ->set('direccion', 'desc')
        ->assertViewHas('productos', fn ($productos): bool => nombresDelCatalogo($productos) === [
            $reciente->nombre, $antiguo->nombre,
        ]);
});
