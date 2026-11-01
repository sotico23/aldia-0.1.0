<?php

use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function createProductoImageUser(): User
{
    $user = User::factory()->create();
    $user->syncRoles([]);
    foreach ([
        'comercial.productos.create',
        'comercial.productos.edit',
        'comercial.productos.delete',
        'comercial.productos.view',
        'comercial.productos.viewAny',
        'comercial.productos.export',
        'comercial.productos.import',
    ] as $permission) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
    }

    return $user;
}

function productoImageUpdateData(array $overrides = []): array
{
    return array_merge([
        'codigo' => 'PRO-IMG-'.fake()->unique()->randomNumber(5),
        'nombre' => 'Producto Imagen',
        'precio_compra' => 1000,
        'precio_venta' => 2500,
        'stock_minimo' => 0,
        'stock' => 0,
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
    $this->user = createProductoImageUser();
    $this->actingAs($this->user);
});

test('update mantiene las imagenes existentes cuando manage_images esta activo', function () {
    $producto = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'codigo' => 'PRO-IMG-KEEP-01',
        'nombre' => 'Producto Con Imagenes',
        'imagen' => 'productos/existing-1.jpg',
        'imagen2' => 'productos/existing-2.jpg',
    ]);

    $this->put(route('productos.update', $producto), [
        'manage_images' => '1',
        'imagenes_existentes' => [
            'imagen' => 'productos/existing-1.jpg',
            'imagen2' => 'productos/existing-2.jpg',
        ],
        'descripcion' => 'Producto de prueba',
        ...productoImageUpdateData(),
    ])->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('productos', [
        'id' => $producto->id,
        'imagen' => 'productos/existing-1.jpg',
        'imagen2' => 'productos/existing-2.jpg',
    ]);
});

test('update elimina fisicamente y en base de datos las imagenes removidas', function () {
    Storage::fake('public');

    $producto = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'codigo' => 'PRO-IMG-DEL-01',
        'nombre' => 'Producto Con Imagen Removible',
        'imagen' => 'productos/to-delete.jpg',
    ]);

    Storage::disk('public')->put('productos/to-delete.jpg', 'content');

    $this->put(route('productos.update', $producto), [
        'manage_images' => '1',
        'imagenes_existentes' => [],
        'descripcion' => 'Producto de prueba',
        ...productoImageUpdateData(),
    ])->assertSessionDoesntHaveErrors();

    Storage::disk('public')->assertMissing('productos/to-delete.jpg');
    $this->assertDatabaseMissing('productos', [
        'id' => $producto->id,
        'imagen' => 'productos/to-delete.jpg',
    ]);
});

test('update reemplaza una imagen existente cuando se sube un nuevo archivo', function () {
    Storage::fake('public');

    $producto = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'codigo' => 'PRO-IMG-REP-01',
        'nombre' => 'Producto Con Imagen Reemplazable',
        'imagen' => 'productos/old-image.jpg',
    ]);

    Storage::disk('public')->put('productos/old-image.jpg', 'content');

    $this->put(route('productos.update', $producto), [
        'manage_images' => '1',
        'imagenes_existentes' => [
            'imagen' => 'productos/old-image.jpg',
        ],
        'descripcion' => 'Producto de prueba',
        'imagen' => UploadedFile::fake()->image('new-image.jpg'),
        ...productoImageUpdateData([
            'codigo' => 'PRO-IMG-REP-02',
        ]),
    ])->assertSessionDoesntHaveErrors();

    Storage::disk('public')->assertMissing('productos/old-image.jpg');

    $producto->refresh();
    expect($producto->imagen)->not->toBe('productos/old-image.jpg');
});
