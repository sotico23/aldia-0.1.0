<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->categoria = Categoria::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'nombre' => 'Cat Test',
    ]);
});

test('it can export productos to csv', function () {
    Producto::factory()->create([
        'codigo' => 'P-EXP-01',
        'nombre' => 'Producto Export',
        'owner_id' => $this->user->getOwnerId(),
        'categoria_id' => $this->categoria->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('productos.export'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
});

test('it can import productos from csv', function () {
    $csvContent = "Codigo,Nombre,Descripcion,Categoria,Precio Compra,Precio Venta,Stock Minimo,Stock,Unidad Medida,Activo\n";
    $csvContent .= 'P-IMP-01,Producto Importado,Desc,Cat Test,1000,2000,10,50,unidad,Si';

    $file = UploadedFile::fake()->createWithContent('import_productos.csv', $csvContent);

    $response = $this->actingAs($this->user)
        ->post(route('productos.import'), [
            'archivo' => $file,
        ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertStatus(302);
    $this->assertDatabaseHas('productos', [
        'codigo' => 'P-IMP-01',
        'nombre' => 'Producto Importado',
    ]);
});
