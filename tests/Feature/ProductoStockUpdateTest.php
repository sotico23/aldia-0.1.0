<?php

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function productoStockPayload(array $overrides = []): array
{
    return array_merge([
        'codigo' => 'PRO-STOCK-'.fake()->unique()->randomNumber(5),
        'nombre' => 'Producto Stock',
        'descripcion' => 'Producto de prueba para actualización de stock',
        'precio_compra' => 1000,
        'precio_venta' => 2500,
        'stock_minimo' => 5,
        'stock' => 10,
        'unidad_medida' => 'unidad',
        'activo' => '1',
        'tiene_variantes' => '0',
        'envase_retornable' => '0',
        'medida_pesable' => '0',
        'mostrar_en_perfil' => '1',
        'contenido_por_unidad' => 1,
        'peso_base' => 0,
    ], $overrides);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->syncRoles([]);

    foreach ([
        'comercial.productos.create',
        'comercial.productos.edit',
        'comercial.productos.delete',
        'comercial.productos.view',
        'comercial.productos.viewAny',
    ] as $permission) {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
    }

    $this->actingAs($this->user);
});

test('actualiza el stock del inventario asociado al editar la ficha', function () {
    $almacen = Almacen::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'activo' => true,
    ]);

    $producto = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'codigo' => 'PRO-STOCK-EDIT-01',
        'tiene_variantes' => false,
        'stock_minimo' => 5,
    ]);

    $inventario = Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'owner_id' => $this->user->getOwnerId(),
        'cantidad' => 0,
        'cantidad_minima' => 5,
    ]);

    $this->put(route('productos.update', $producto), productoStockPayload([
        'codigo' => $producto->codigo,
        'stock' => 37,
        'stock_minimo' => 5,
        'warehouse_ids' => [$almacen->id],
    ]))->assertSessionDoesntHaveErrors();

    $inventario->refresh();

    expect((float) $inventario->cantidad)->toBe(37.0)
        ->and((float) $inventario->cantidad_minima)->toBe(5.0)
        ->and($inventario->owner_id)->toBe($this->user->getOwnerId())
        ->and((float) $producto->fresh()->stock)->toBe(37.0)
        ->and((float) $producto->fresh()->stock_total)->toBe(37.0);
});

test('sincroniza el stock ingresado en todos los almacenes seleccionados', function () {
    $almacenOrigen = Almacen::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'activo' => true,
    ]);
    $almacenNuevo = Almacen::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'activo' => true,
    ]);

    $producto = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'codigo' => 'PRO-STOCK-EDIT-02',
        'tiene_variantes' => false,
    ]);

    Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacenOrigen->id,
        'owner_id' => $this->user->getOwnerId(),
        'cantidad' => 3,
        'cantidad_minima' => 1,
    ]);

    $this->put(route('productos.update', $producto), productoStockPayload([
        'codigo' => $producto->codigo,
        'stock' => 18,
        'stock_minimo' => 2,
        'warehouse_ids' => [$almacenOrigen->id, $almacenNuevo->id],
    ]))->assertSessionDoesntHaveErrors();

    $inventarios = $producto->inventarios()->get();

    expect($inventarios)->toHaveCount(2)
        ->and($inventarios->every(fn (Inventario $inventario) => (float) $inventario->cantidad === 18.0))->toBeTrue()
        ->and($inventarios->every(fn (Inventario $inventario) => (float) $inventario->cantidad_minima === 2.0))->toBeTrue();
});

test('actualiza el inventario existente aunque no se envíen almacenes', function () {
    $almacen = Almacen::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'activo' => true,
    ]);

    $producto = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'codigo' => 'PRO-STOCK-EDIT-03',
        'tiene_variantes' => false,
    ]);

    $inventario = Inventario::factory()->create([
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'owner_id' => $this->user->getOwnerId(),
        'cantidad' => 4,
        'cantidad_minima' => 1,
    ]);

    $this->put(route('productos.update', $producto), productoStockPayload([
        'codigo' => $producto->codigo,
        'stock' => 42,
        'stock_minimo' => 1,
    ]))->assertSessionDoesntHaveErrors();

    $inventario->refresh();

    expect((float) $inventario->cantidad)->toBe(42.0)
        ->and((float) $producto->fresh()->stock_total)->toBe(42.0);
});

test('crea el inventario en el almacén activo cuando el producto no tiene stock asociado', function () {
    $almacen = Almacen::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'activo' => true,
    ]);

    $producto = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'codigo' => 'PRO-STOCK-EDIT-04',
        'tiene_variantes' => false,
    ]);

    $this->put(route('productos.update', $producto), productoStockPayload([
        'codigo' => $producto->codigo,
        'stock' => 15,
        'stock_minimo' => 1,
    ]))->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('inventarios', [
        'producto_id' => $producto->id,
        'almacen_id' => $almacen->id,
        'owner_id' => $this->user->getOwnerId(),
        'cantidad' => 15,
    ]);

    expect((float) $producto->fresh()->stock_total)->toBe(15.0);
});
