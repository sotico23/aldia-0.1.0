<?php

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Models\Variante;
use App\Models\VarianteValor;
use Spatie\Permission\Models\Permission;

function giveProductoPermissions(User $user): void
{
    $permissions = [
        'comercial.productos.create',
        'comercial.productos.edit',
        'comercial.productos.delete',
        'comercial.productos.view',
        'comercial.productos.viewAny',
        'comercial.productos.export',
        'comercial.productos.import',
    ];
    $permissions = array_map(fn ($name) => Permission::firstOrCreate(['name' => $name]), $permissions);
    $user->givePermissionTo($permissions);
}

function createProductoUser(): User
{
    $user = User::factory()->create();
    $user->syncRoles([]);
    giveProductoPermissions($user);

    return $user;
}

function defaultProductData(array $overrides = []): array
{
    return array_merge([
        'codigo' => 'PRO-TEST-'.fake()->unique()->randomNumber(5),
        'nombre' => 'Producto Test',
        'categoria_id' => null,
        'precio_compra' => 1000,
        'precio_venta' => 2500,
        'stock_minimo' => 5,
        'stock' => 10,
        'unidad_medida' => 'unidad',
        'activo' => true,
        'tiene_variantes' => '0',
        'envase_retornable' => '0',
        'medida_pesable' => '0',
        'mostrar_en_perfil' => '1',
        'contenido_por_unidad' => 1,
        'peso_base' => 0,
    ], $overrides);
}

beforeEach(function () {
    $this->user = createProductoUser();
    $this->actingAs($this->user);
});

test('crea producto basico sin variantes', function () {
    $this->post(route('productos.store'), defaultProductData([
        'codigo' => 'PRO-BASIC-01',
        'nombre' => 'Producto Basico',
    ]))->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('productos', [
        'codigo' => 'PRO-BASIC-01',
        'nombre' => 'Producto Basico',
        'precio_venta' => 2500.00,
    ]);
});

test('crea producto con variantes existentes', function () {
    $almacen = Almacen::factory()->create(['owner_id' => $this->user->id, 'activo' => true]);

    $variante = Variante::create([
        'owner_id' => $this->user->getOwnerId(),
        'nombre' => 'Talla',
        'tipo' => 'texto',
    ]);
    $valor1 = VarianteValor::create(['variante_id' => $variante->id, 'valor' => 'S']);
    $valor2 = VarianteValor::create(['variante_id' => $variante->id, 'valor' => 'M']);

    $this->post(route('productos.store'), defaultProductData([
        'codigo' => 'PRO-VAR-01',
        'nombre' => 'Producto con Variantes',
        'stock' => 0,
        'tiene_variantes' => '1',
        'variante_ids' => [$variante->id],
        'skus' => [
            [
                'sku' => 'PRO-VAR-01-S',
                'precio_venta' => 5000,
                'precio_compra' => 2000,
                'stock' => 10,
                'stock_minimo' => 2,
                'variante_valores' => [$valor1->id],
            ],
            [
                'sku' => 'PRO-VAR-01-M',
                'precio_venta' => 5500,
                'precio_compra' => 2200,
                'stock' => 15,
                'stock_minimo' => 3,
                'variante_valores' => [$valor2->id],
            ],
        ],
    ]))->assertSessionDoesntHaveErrors();

    $producto = Producto::where('codigo', 'PRO-VAR-01')->with('skus.valores')->first();
    expect($producto)->not->toBeNull();
    expect((bool) $producto->tiene_variantes)->toBeTrue();
    expect($producto->skus)->toHaveCount(2);

    $sku1 = $producto->skus->firstWhere('sku', 'PRO-VAR-01-S');
    expect((float) $sku1->stock)->toBe(10.0);
    expect((float) $sku1->stock_minimo)->toBe(2.0);
    expect($sku1->valores)->toHaveCount(1);
    expect($sku1->valores->first()->variante_valor_id)->toBe($valor1->id);
});

test('crea producto con variantes inline', function () {
    $this->post(route('productos.store'), defaultProductData([
        'codigo' => 'PRO-INLINE-01',
        'nombre' => 'Producto Inline',
        'stock' => 0,
        'tiene_variantes' => '1',
        'variantes' => [
            [
                'nombre' => 'Color',
                'tipo' => 'texto',
                'valores' => ['Rojo', 'Azul'],
            ],
        ],
    ]))->assertSessionDoesntHaveErrors();

    $producto = Producto::where('codigo', 'PRO-INLINE-01')->first();
    expect($producto)->not->toBeNull();
    expect((bool) $producto->tiene_variantes)->toBeTrue();
});

test('valida errores en variantes invalidas', function () {
    $this->post(route('productos.store'), defaultProductData([
        'codigo' => 'PRO-ERR-01',
        'tiene_variantes' => '1',
        'variante_ids' => [999],
        'skus' => [
            [
                'sku' => 'PRO-ERR-01-X',
                'stock' => 5,
                'variante_valores' => [999],
            ],
        ],
    ]))->assertSessionHasErrors(['variante_ids.0', 'skus.0.variante_valores.0']);
});

test('edita producto quitando variantes', function () {
    $categoria = Categoria::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'nombre' => 'Edit Cat',
    ]);
    $producto = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'codigo' => 'PRO-EDIT-01',
        'nombre' => 'Editable',
        'categoria_id' => $categoria->id,
        'tiene_variantes' => false,
    ]);

    $this->put(route('productos.update', $producto), [
        'codigo' => 'PRO-EDIT-01',
        'nombre' => 'Editable Updated',
        'descripcion' => $producto->descripcion,
        'precio_compra' => 1500,
        'precio_venta' => 3000,
        'stock_minimo' => 8,
        'stock' => 20,
        'unidad_medida' => 'unidad',
        'activo' => '1',
        'tiene_variantes' => '0',
        'envase_retornable' => '0',
        'medida_pesable' => '0',
        'mostrar_en_perfil' => '1',
        'contenido_por_unidad' => 1,
        'peso_base' => 0,
    ])->assertSessionDoesntHaveErrors();

    $producto->refresh();
    expect($producto->nombre)->toBe('Editable Updated');
    expect((bool) $producto->tiene_variantes)->toBeFalse();
});

test('producto sin permiso create no puede crear', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);
    $this->actingAs($user);

    $this->post(route('productos.store'), defaultProductData([
        'codigo' => 'PRO-NOPERM-01',
    ]))->assertForbidden();
});
