<?php

use App\Livewire\Productos\Index;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['comercial.productos.viewAny', 'comercial.productos.create', 'comercial.productos.edit', 'comercial.productos.delete'] as $permiso) {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permiso]));
    }
    $this->actingAs($this->user);
});

test('crea un producto desde el modal Livewire', function () {
    Livewire::test(Index::class)
        ->call('nueva')
        ->set('form.codigo', 'LIVE-001')
        ->set('form.nombre', 'Producto Livewire')
        ->set('form.precio_compra', 1000)
        ->set('form.precio_venta', 2000)
        ->set('form.stock', 10)
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertSet('mensaje', 'Producto creado correctamente.')
        ->assertNoRedirect();

    $this->assertDatabaseHas('productos', [
        'codigo' => 'LIVE-001',
        'nombre' => 'Producto Livewire',
        'owner_id' => $this->user->getOwnerId(),
    ]);
});

test('editar y ver muestran y actualizan el producto', function () {
    $producto = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'codigo' => 'LIVE-002',
        'nombre' => 'Producto original',
    ]);

    Livewire::test(Index::class)
        ->call('ver', $producto->id)
        ->assertSee('Detalle de producto')
        ->assertSee('Producto original')
        ->call('cerrarDetalle')
        ->call('editar', $producto->id)
        ->set('form.nombre', 'Producto editado')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertSet('mensaje', 'Producto actualizado correctamente.');

    expect($producto->fresh()->nombre)->toBe('Producto editado');
});

test('elimina un producto desde la lista', function () {
    $producto = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'codigo' => 'LIVE-003',
    ]);

    Livewire::test(Index::class)
        ->call('eliminar', $producto->id)
        ->assertSet('mensaje', 'Producto eliminado correctamente.')
        ->assertNoRedirect();

    $this->assertSoftDeleted('productos', ['id' => $producto->id]);
});

test('una acción rechaza productos de otra empresa', function () {
    $otro = User::factory()->create();
    $ajeno = Producto::factory()->create([
        'codigo' => 'LIVE-AJENO',
        'user_id' => $otro->id,
        'owner_id' => $otro->getOwnerId(),
    ]);

    expect(fn () => Livewire::test(Index::class)->call('editar', $ajeno->id))
        ->toThrow(ModelNotFoundException::class);
});
