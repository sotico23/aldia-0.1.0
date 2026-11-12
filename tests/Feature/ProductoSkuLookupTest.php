<?php

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['comercial.productos.viewAny', 'comercial.productos.create'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $this->user = User::factory()->create();
    $this->user->syncRoles([]);
    $this->user->givePermissionTo([
        'comercial.productos.viewAny',
        'comercial.productos.create',
    ]);
});

test('sku lookup hydrates full product data when the sku exists locally', function () {
    Storage::fake('public');

    $categoria = Categoria::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'tipo' => 'producto',
        'nombre' => 'Bebidas',
    ]);

    $almacen = Almacen::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'nombre' => 'Almacén Principal',
        'activo' => true,
    ]);

    $producto = Producto::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->user->getOwnerId(),
        'codigo' => 'PRO-001',
        'nombre' => 'Gaseosa de cola 500ml',
        'descripcion' => 'Refresco sabor cola',
        'categoria_id' => $categoria->id,
        'precio_compra' => 1200.00,
        'precio_venta' => 2000.00,
        'stock_minimo' => 10,
        'unidad_medida' => 'unidad',
        'imagen' => 'productos/imagen.jpg',
        'imagen2' => 'productos/imagen2.jpg',
        'activo' => true,
    ]);

    $producto->inventarios()->create([
        'almacen_id' => $almacen->id,
        'cantidad' => 50,
        'cantidad_minima' => 10,
        'owner_id' => $this->user->getOwnerId(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.v1.productos.lookup-sku', ['codigo' => 'PRO-001']))
        ->assertSuccessful()
        ->assertJsonPath('encontrado', true)
        ->assertJsonPath('origen', 'base_datos')
        ->assertJsonPath('datos.codigo', 'PRO-001')
        ->assertJsonPath('datos.nombre', 'Gaseosa de cola 500ml')
        ->assertJsonPath('datos.descripcion', 'Refresco sabor cola')
        ->assertJsonPath('datos.categoria_id', $categoria->id)
        ->assertJsonPath('datos.precio_compra', 1200.00)
        ->assertJsonPath('datos.precio_venta', 2000.00)
        ->assertJsonPath('datos.stock_minimo', 10)
        ->assertJsonPath('datos.unidad_medida', 'unidad')
        ->assertJsonPath('datos.tiene_variantes', false)
        ->assertJsonPath('datos.inventarios.0.almacen_id', $almacen->id)
        ->assertJsonPath('datos.inventarios.0.cantidad', 50.0)
        ->assertJsonNotNull('datos.imagen');
});

test('sku lookup returns not found with a subtle notice when the sku does not exist', function () {
    $this->actingAs($this->user)
        ->getJson(route('api.v1.productos.lookup-sku', ['codigo' => 'INEEXISTENTE']))
        ->assertSuccessful()
        ->assertJsonPath('encontrado', false)
        ->assertJsonPath('origen', 'base_datos')
        ->assertJsonPath('datos', null)
        ->assertJsonPath('mensaje', 'SKU no registrado. Puede completar el producto manualmente.');
});

test('sku lookup respects tenant isolation (other tenant cannot see the product)', function () {
    $otroUsuario = User::factory()->create();
    $otroUsuario->syncRoles([]);
    $otroUsuario->givePermissionTo([
        'comercial.productos.viewAny',
        'comercial.productos.create',
    ]);

    Producto::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->user->getOwnerId(),
        'codigo' => 'PRO-002',
        'nombre' => 'Producto del tenant A',
    ]);

    $this->actingAs($otroUsuario)
        ->getJson(route('api.v1.productos.lookup-sku', ['codigo' => 'PRO-002']))
        ->assertSuccessful()
        ->assertJsonPath('encontrado', false);
});

test('sku lookup requires product creation permission', function () {
    $usuarioSinPermiso = User::factory()->create();
    $usuarioSinPermiso->syncRoles([]);
    $usuarioSinPermiso->givePermissionTo('comercial.productos.viewAny');

    $this->actingAs($usuarioSinPermiso)
        ->getJson(route('api.v1.productos.lookup-sku', ['codigo' => 'PRO-003']))
        ->assertForbidden();
});

test('sku lookup rejects invalid sku characters', function () {
    $this->actingAs($this->user)
        ->getJson(route('api.v1.productos.lookup-sku', ['codigo' => 'PRO@001']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('codigo');
});
