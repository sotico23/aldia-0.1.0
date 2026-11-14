<?php

use App\Models\PublicProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

uses(TestCase::class);

test('la cabecera muestra las fotos del propietario y no las del visitante', function (?string $cover, ?string $photo) {
    $owner = User::factory()->make([
        'id' => 11, 'name' => 'Dueño Tienda',
        'cover_photo_path' => $cover, 'profile_photo_path' => $photo,
    ]);
    $visitor = User::factory()->make([
        'id' => 22,
        'cover_photo_path' => 'photos/visitante-portada.jpg',
        'profile_photo_path' => 'photos/visitante-perfil.jpg',
    ]);
    $store = PublicProfile::factory()->make(['id' => 33, 'user_id' => $owner->id, 'owner_id' => $owner->id]);
    $store->setRelation('user', $owner);
    $this->actingAs($visitor);

    $view = $this->view('livewire.marketplace-store', [
        'store' => $store,
        'almacenes' => collect(), 'categorias' => collect(), 'productos' => collect(),
        'reviews' => collect(), 'totalProductos' => 0,
        'itemsCarrito' => [], 'totalCarrito' => 0, 'unidadesCarrito' => 0,
        'promedio' => '5.0', 'haComprado' => false, 'yaOpino' => false,
        'metodosPago' => [], 'usuarioAutenticado' => true,
        'tabActivo' => 'productos', 'filtroAlmacen' => null, 'filtroCategoria' => null,
    ]);

    $view->assertSee('src="'.e($owner->profilePhotoUrl()).'"', false)
        ->assertDontSee($visitor->profilePhotoUrl())
        ->assertDontSee($visitor->coverPhotoUrl());

    if ($cover) {
        $view->assertSee('src="'.e($owner->coverPhotoUrl()).'"', false);
    } else {
        $view->assertDontSee('alt="Portada de', false);
    }
})->with([
    'archivos locales' => ['covers/propietario.jpg', 'photos/propietario.jpg'],
    'urls externas' => ['https://images.example.com/portada.jpg', 'https://images.example.com/perfil.jpg'],
    'sin fotos' => [null, null],
]);
