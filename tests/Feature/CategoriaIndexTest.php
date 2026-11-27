<?php

use App\Livewire\Categorias\Index;
use App\Models\Categoria;
use App\Models\PublicProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
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

test('la página se abre como Livewire y expulsa a Inertia', function () {
    $this->withoutVite();

    $this->get(route('categorias.index'))
        ->assertOk()
        ->assertSeeLivewire(Index::class)
        ->assertSee('livewire.csp', false);

    $this->get(route('categorias.index'), ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('categorias.index'));
});

test('agrupa las categorías por tipo con resumen', function () {
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

    Livewire::test(Index::class)
        ->assertViewHas('porTipo', function ($grupos): bool {
            return $grupos->count() === 2
                && $grupos[0]['tipo'] === 'producto'
                && $grupos[0]['total'] === 2
                && $grupos[1]['tipo'] === 'cliente'
                && $grupos[1]['total'] === 1;
        })
        ->assertViewHas('resumen', fn (array $resumen): bool => $resumen['total'] === 3
            && $resumen['marketplace'] === 2
            && $resumen['tipos'] === 2);
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

    Livewire::test(Index::class)
        ->set('marketplace', '1')
        ->assertViewHas('categorias', fn ($categorias): bool => $categorias->total() === 1);
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

    Livewire::test(Index::class)
        ->set('busqueda', 'jugos')
        ->assertViewHas('categorias', fn ($categorias): bool => $categorias->total() === 1)
        ->assertSee('Bebidas')
        ->assertDontSee('Lácteos');
});

test('crea, edita y elimina categorías sin redirigir', function () {
    foreach (['comercial.categorias.create', 'comercial.categorias.edit', 'comercial.categorias.delete'] as $permiso) {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permiso]));
    }
    $categoria = crearCategoriaDePrueba($this->user, ['nombre' => 'Categoría original']);

    Livewire::test(Index::class)
        ->call('nueva')
        ->set('form.nombre', 'Categoría creada')
        ->set('form.tipo', 'servicio')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertSet('mensaje', 'Categoría creada.')
        ->assertNoRedirect()
        ->call('editar', $categoria->id)
        ->set('form.nombre', 'Categoría editada')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertSet('mensaje', 'Categoría actualizada.')
        ->call('eliminar', $categoria->id)
        ->assertSet('mensaje', 'Categoría eliminada.')
        ->assertNoRedirect();

    expect(Categoria::find($categoria->id))->toBeNull()
        ->and(Categoria::where('nombre', 'Categoría creada')->exists())->toBeTrue();
});

test('una acción rechaza categorías de otra empresa', function () {
    $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'comercial.categorias.edit']));
    $ajena = crearCategoriaDePrueba(User::factory()->create());

    expect(fn () => Livewire::test(Index::class)->call('editar', $ajena->id))
        ->toThrow(ModelNotFoundException::class);
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
