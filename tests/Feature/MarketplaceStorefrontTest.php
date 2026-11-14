<?php

use App\Livewire\MarketplaceStore;
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
use Livewire\Livewire;

test('las visitas inertia al perfil publico solicitan una carga completa', function () {
    $url = route('marketplace.show', 'tienda-publica');

    $this->withHeader('X-Inertia', 'true')
        ->get($url)
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', $url);
});

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

    $this->almacen = Almacen::factory()->create([
        'owner_id' => $this->vendor->id,
        'activo' => true,
    ]);

    Inventario::factory()->create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $this->almacen->id,
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

test('la tienda renderiza el catalogo livewire con el stock real', function () {
    $this->get(route('marketplace.show', $this->publicProfile->slug))
        ->assertSuccessful()
        ->assertSeeLivewire('marketplace-store')
        ->assertSee('Botella de prueba')
        ->assertSee('12 disponibles');
});

test('el componente calcula el stock sumando los inventarios del producto', function () {
    Livewire::test(MarketplaceStore::class, ['slug' => $this->publicProfile->slug])
        ->assertViewHas('totalProductos', 1)
        ->call('agregar', $this->producto->id)
        ->assertSet('carrito', [$this->producto->id => 1]);
});

test('la tienda lista todos los productos de su propietario sin aplicar filtros de visibilidad', function () {
    $productoLegado = Producto::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'categoria_id' => $this->categoria->id,
        'public_profile_id' => null,
        'codigo' => 'PRO-STORE-LEGACY',
        'nombre' => 'Producto legado',
    ]);

    $productoNoVisible = Producto::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'categoria_id' => $this->categoria->id,
        'public_profile_id' => $this->publicProfile->id,
        'codigo' => 'PRO-STORE-02',
        'nombre' => 'Producto no visible',
        'activo' => false,
        'mostrar_en_perfil' => false,
    ]);

    Producto::factory()->count(13)->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'categoria_id' => $this->categoria->id,
        'public_profile_id' => $this->publicProfile->id,
        'nombre' => 'zz producto masivo',
    ]);

    Producto::factory()->create([
        'user_id' => $this->buyer->id,
        'owner_id' => $this->buyer->id,
        'categoria_id' => $this->categoria->id,
        'public_profile_id' => $this->publicProfile->id,
        'codigo' => 'PRO-STORE-03',
        'nombre' => 'Producto de otro propietario',
    ]);

    Livewire::test(MarketplaceStore::class, ['slug' => $this->publicProfile->slug])
        ->assertViewHas('totalProductos', 16)
        ->assertSee('Producto legado')
        ->assertSee('Producto no visible')
        ->assertSee($productoLegado->nombre)
        ->assertSee($productoNoVisible->nombre)
        ->assertDontSee('Producto de otro propietario');
});

test('el filtro por almacen limita los productos mostrados', function () {
    $otroAlmacen = Almacen::factory()->create([
        'owner_id' => $this->vendor->id,
        'activo' => true,
    ]);

    Inventario::factory()->create([
        'producto_id' => $this->producto->id,
        'almacen_id' => $otroAlmacen->id,
        'owner_id' => $this->vendor->id,
        'cantidad' => 5,
        'cantidad_minima' => 1,
    ]);

    $productoSinInventario = Producto::factory()->create([
        'user_id' => $this->vendor->id,
        'owner_id' => $this->vendor->id,
        'categoria_id' => $this->categoria->id,
        'public_profile_id' => $this->publicProfile->id,
        'nombre' => 'Producto sin inventario',
    ]);

    Livewire::test(MarketplaceStore::class, ['slug' => $this->publicProfile->slug])
        ->call('setFiltroAlmacen', $otroAlmacen->id)
        ->assertViewHas('totalProductos', 1)
        ->assertSee('Botella de prueba')
        ->assertDontSee($productoSinInventario->nombre);
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
