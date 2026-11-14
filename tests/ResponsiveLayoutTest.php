<?php

use App\Livewire\MarketplaceStore;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;
use App\Models\WebSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(TestCase::class);

class ResponsiveMarketplaceFixture extends MarketplaceStore
{
    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(): View
    {
        $owner = User::factory()->make(['id' => 11, 'name' => 'Dueño de tienda']);
        $store = PublicProfile::factory()->make([
            'id' => 33, 'title' => 'Tienda con un nombre largo para probar la navegación',
            'slug' => 'tienda-responsive', 'user_id' => $owner->id, 'owner_id' => $owner->id,
        ]);
        $store->setRelation('user', $owner);
        $productos = collect(range(1, 6))->map(function (int $id) use ($owner, $store): Producto {
            $producto = Producto::factory()->make([
                'id' => $id, 'nombre' => 'Producto de prueba '.$id,
                'user_id' => $owner->id, 'owner_id' => $owner->id, 'categoria_id' => 1,
                'public_profile_id' => $store->id, 'is_service' => false, 'imagen' => null,
            ]);
            $producto->setRelation('inventarios', collect([
                Inventario::factory()->make(['producto_id' => $id, 'cantidad' => 10]),
            ]));

            return $producto;
        });

        return view('livewire.marketplace-store', [
            'store' => $store, 'productos' => $productos, 'totalProductos' => $productos->count(),
            'almacenes' => collect(), 'categorias' => collect(), 'reviews' => collect(),
            'itemsCarrito' => [], 'totalCarrito' => 0, 'unidadesCarrito' => 0,
            'promedio' => '5.0', 'haComprado' => false, 'yaOpino' => false,
            'metodosPago' => [], 'usuarioAutenticado' => false,
        ]);
    }
}

test('la tienda mantiene productos filtros y carrito dentro del layout compartido', function () {
    if (! getenv('RESPONSIVE_FIXTURE_DIR')) {
        $this->withoutVite();
    }

    Cache::forever('web_settings:latest', new WebSetting(['app_name' => 'RedCliente']));
    Livewire::component('responsive-marketplace-fixture', ResponsiveMarketplaceFixture::class);

    $view = $this->blade('<x-app-layout :breadcrumbs="[[\'title\' => \'Marketplace\', \'href\' => \'/tienda\'], [\'title\' => \'Tienda con un nombre largo para probar la navegación\', \'href\' => \'#\']]"><livewire:responsive-marketplace-fixture slug="tienda-responsive" /></x-app-layout>');

    $view->assertSee('Producto de prueba 1')->assertSee('Producto de prueba 6')
        ->assertSee('Filtros de productos')->assertSee('Agregar Producto de prueba 1 al carrito')
        ->assertSee('Cerrar carrito')->assertSee('Abrir mi cuenta')->assertSee('Abrir navegación');

    if ($directory = getenv('RESPONSIVE_FIXTURE_DIR')) {
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($directory.'/store.html', (string) $view);
    }
});
