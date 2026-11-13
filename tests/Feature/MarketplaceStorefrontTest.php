<?php

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\SkuVariante;
use App\Models\SkuVarianteValor;
use App\Models\User;
use App\Models\Variante;
use App\Models\VarianteValor;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->vendor = User::factory()->create();
    $this->buyer = User::factory()->create();

    $this->publicProfile = PublicProfile::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'is_active' => true,
    ]);

    $this->categoria = Categoria::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'nombre' => 'Accesorios',
        'activo' => true,
        'mostrar_en_perfil' => true,
        'public_profile_id' => $this->publicProfile->id,
    ]);

    $this->producto = Producto::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'categoria_id' => $this->categoria->id,
        'public_profile_id' => $this->publicProfile->id,
        'codigo' => 'PRO-STORE-01',
        'nombre' => 'Botella de prueba',
        'descripcion' => 'Descripción ampliada del producto para la tienda pública.',
        'imagen' => 'productos/principal.jpg',
        'imagen2' => 'productos/secundaria.jpg',
        'precio_venta' => 4990,
        'activo' => true,
        'mostrar_en_perfil' => true,
        'tiene_variantes' => true,
    ]);

    $almacen = Almacen::factory()->create([
        'owner_id' => $this->vendor->id,
        'activo' => true,
    ]);

    Inventario::factory()->create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $almacen->id,
        'owner_id' => $this->vendor->id,
        'cantidad' => 12,
        'cantidad_minima' => 2,
    ]);

    $variante = Variante::create([
        'owner_id' => $this->vendor->id,
        'nombre' => 'Color',
        'tipo' => 'texto',
        'activo' => true,
    ]);

    $valor = VarianteValor::create([
        'variante_id' => $variante->id,
        'valor' => 'Rojo',
    ]);

    $sku = SkuVariante::create([
        'producto_id' => $this->producto->id,
        'sku' => 'PRO-STORE-01-ROJO',
        'precio_venta' => 4990,
        'precio_compra' => 2500,
        'stock' => 8,
        'stock_minimo' => 1,
        'activo' => true,
    ]);

    SkuVarianteValor::create([
        'sku_variante_id' => $sku->id,
        'variante_valor_id' => $valor->id,
    ]);
});

test('la tienda expone los datos completos del producto para la vista de detalle', function () {
    $this->actingAs($this->buyer)
        ->get(route('marketplace.show', $this->publicProfile->slug))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/show')
            ->where('store.productos.0.codigo', 'PRO-STORE-01')
            ->where('store.productos.0.descripcion', 'Descripción ampliada del producto para la tienda pública.')
            ->where('store.productos.0.imagen', 'productos/principal.jpg')
            ->where('store.productos.0.imagen2', 'productos/secundaria.jpg')
            ->where('store.productos.0.inventarios.0.cantidad', 12)
            ->where('store.productos.0.skus.0.sku', 'PRO-STORE-01-ROJO')
            ->where('store.productos.0.skus.0.valores.0.variante_valor.valor', 'Rojo')
            ->has('almacenes', 1)
            ->where('almacenes.0.activo', true)
        );
});

test('la ruta de todas las categorias lista las categorias visibles de la tienda', function () {
    $this->get(route('marketplace.categories', $this->publicProfile->slug))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/categories')
            ->has('categorias', 1)
            ->where('categorias.0.nombre', 'Accesorios')
        );
});
