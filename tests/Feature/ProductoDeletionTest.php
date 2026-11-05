<?php

use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->syncRoles([]);
    $this->user->givePermissionTo([
        Permission::firstOrCreate(['name' => 'comercial.productos.delete']),
        Permission::firstOrCreate(['name' => 'comercial.productos.viewAny']),
    ]);
    $this->actingAs($this->user);
});

test('deleting a product with sales preserves its history and media', function () {
    Storage::fake('public');

    $product = Producto::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'imagen' => 'productos/producto.jpg',
    ]);
    Storage::disk('public')->put('productos/producto.jpg', 'image content');

    $saleItem = DetalleVenta::factory()->create([
        'producto_id' => $product->id,
    ]);

    $response = $this->delete(route('productos.destroy', $product));

    $response->assertRedirect(route('productos.index'));
    $response->assertSessionHas('success', 'Producto eliminado correctamente.');
    $this->assertSoftDeleted('productos', ['id' => $product->id]);
    $this->assertDatabaseHas('detalle_ventas', ['id' => $saleItem->id, 'producto_id' => $product->id]);
    Storage::disk('public')->assertExists('productos/producto.jpg');
    expect($saleItem->fresh()->producto)->not->toBeNull();
});
