<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->categoria = Categoria::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'nombre' => 'Cat Venta',
    ]);
    $this->cliente = Cliente::factory()->create([
        'email' => 'venta@test.com',
        'owner_id' => $this->user->getOwnerId(),
        'categoria_id' => $this->categoria->id,
    ]);
    $this->producto = Producto::factory()->create([
        'codigo' => 'PROD-V',
        'nombre' => 'Producto Venta',
        'precio_venta' => 1000,
        'owner_id' => $this->user->getOwnerId(),
        'categoria_id' => $this->categoria->id,
    ]);
});

test('it can export ventas to csv', function () {
    Venta::factory()->create([
        'numero' => 'V-EXP-01',
        'cliente_id' => $this->cliente->id,
        'owner_id' => $this->user->getOwnerId(),
        'total' => 1190,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('ventas.export'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('it can import ventas from csv', function () {
    $csvContent = "Numero;Fecha;Cliente_Email;Estado;Item_Descripcion;Item_Cantidad;Item_Precio;Subtotal;IVA;Total;Notas\n";
    $csvContent .= 'V-IMP-01;2024-04-01;venta@test.com;pagada;Producto Venta;1;1000;1000;190;1190;Importada';

    $file = UploadedFile::fake()->createWithContent('import_ventas.csv', $csvContent);

    $response = $this->actingAs($this->user)
        ->post(route('ventas.import'), [
            'archivo' => $file,
        ]);

    $response->assertStatus(302);
    $this->assertDatabaseHas('ventas', [
        'numero' => 'V-IMP-01',
        'total' => 1190,
    ]);
});
