<?php

use App\Models\Categoria;
use App\Models\PublicProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->syncRoles([]);
    $this->user->givePermissionTo(
        Permission::firstOrCreate(['name' => 'comercial.categorias.viewAny'])
    );
    $this->actingAs($this->user);
});

function crearCategoriaDePrueba(User $user, array $attributes = []): Categoria
{
    return Categoria::factory()->create(array_merge([
        'owner_id' => $user->id,
        'activo' => true,
        'mostrar_en_perfil' => false,
    ], $attributes));
}

test('agrupa las categorías por tipo con resumen y tipos dinámicos', function () {
    $profile = PublicProfile::factory()->create(['user_id' => $this->user->id]);

    crearCategoriaDePrueba($this->user, [
        'tipo' => 'producto',
        'public_profile_id' => $profile->id,
        'mostrar_en_perfil' => true,
    ]);
    crearCategoriaDePrueba($this->user, [
        'tipo' => 'producto',
        'public_profile_id' => $profile->id,
        'mostrar_en_perfil' => true,
    ]);
    crearCategoriaDePrueba($this->user, [
        'tipo' => 'cliente',
        'public_profile_id' => $profile->id,
        'mostrar_en_perfil' => false,
    ]);

    $this->get(route('categorias.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Categorias/Index')
            ->has('categoriasPorTipo', 2)
            ->where('categoriasPorTipo.0.tipo', 'producto')
            ->where('categoriasPorTipo.0.total', 2)
            ->where('categoriasPorTipo.0.marketplace', 2)
            ->where('categoriasPorTipo.1.tipo', 'cliente')
            ->where('categoriasPorTipo.1.total', 1)
            ->where('resumen.total', 3)
            ->where('resumen.marketplace', 2)
            ->where('resumen.tipos', 2)
            ->has('tiposDisponibles', 4)
        );
});

test('filtra las categorías publicadas en el marketplace', function () {
    $profile = PublicProfile::factory()->create(['user_id' => $this->user->id]);

    crearCategoriaDePrueba($this->user, [
        'tipo' => 'producto',
        'public_profile_id' => $profile->id,
        'mostrar_en_perfil' => true,
    ]);
    crearCategoriaDePrueba($this->user, [
        'tipo' => 'producto',
        'public_profile_id' => $profile->id,
        'mostrar_en_perfil' => false,
    ]);

    $this->get(route('categorias.index', ['marketplace' => '1']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('categoriasPorTipo', 1)
            ->where('categoriasPorTipo.0.total', 1)
            ->where('resumen.marketplace', 1)
        );
});

test('busca categorías por nombre o descripción', function () {
    crearCategoriaDePrueba($this->user, [
        'tipo' => 'producto',
        'nombre' => 'Bebidas',
        'descripcion' => 'Aguas y jugos naturales',
    ]);
    crearCategoriaDePrueba($this->user, [
        'tipo' => 'producto',
        'nombre' => 'Lácteos',
        'descripcion' => 'Quesos y leches',
    ]);

    $this->get(route('categorias.index', ['search' => 'jugos']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('categoriasPorTipo', 1)
            ->where('categoriasPorTipo.0.total', 1)
            ->where('resumen.total', 1)
        );
});

test('crea categorías con los tipos permitidos y rechaza tipos desconocidos', function () {
    $this->user->givePermissionTo(
        Permission::firstOrCreate(['name' => 'comercial.categorias.create'])
    );

    $this->post(route('categorias.store'), [
        'nombre' => 'Categoría de servicio',
        'descripcion' => 'Categoría para servicios',
        'tipo' => 'servicio',
        'activo' => true,
        'mostrar_en_perfil' => true,
    ])->assertRedirect(route('categorias.index'));

    $this->assertDatabaseHas('categorias', [
        'nombre' => 'Categoría de servicio',
        'tipo' => 'servicio',
    ]);

    $this->post(route('categorias.store'), [
        'nombre' => 'Categoría inválida',
        'tipo' => 'tipo-inexistente',
    ])->assertSessionHasErrors('tipo');
});
