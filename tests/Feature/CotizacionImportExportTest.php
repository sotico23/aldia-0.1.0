<?php

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->categoria = Categoria::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'tipo' => 'cliente',
    ]);
    $this->cliente = Cliente::factory()->create([
        'email' => 'test@cliente.com',
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'categoria_id' => $this->categoria->id,
        'activo' => true,
    ]);
});

test('it can export cotizaciones to csv', function () {
    $cot = Cotizacion::factory()->create([
        'numero' => 'COT-EXP-01',
        'cliente_id' => $this->cliente->id,
        'owner_id' => $this->user->getOwnerId(),
        'fecha' => now(),
        'detalles' => [['descripcion' => 'Item 1', 'cantidad' => 2, 'precio' => 1000]],
        'subtotal' => 2000,
        'total' => 2380,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('cotizaciones.export'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
});

test('it can export cotizaciones to excel (html format)', function () {
    Cotizacion::factory()->create([
        'numero' => 'COT-XL-01',
        'cliente_id' => $this->cliente->id,
        'owner_id' => $this->user->getOwnerId(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('cotizaciones.export_excel'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('it can import cotizaciones from comma delimited csv', function () {
    $csvContent = "Numero,Fecha,Vence,Cliente_Email,Estado,Item_Descripcion,Item_Cantidad,Item_Precio,Condiciones,Notas\n";
    $csvContent .= 'COT-COMMA-01,2024-04-01,2024-04-15,test@cliente.com,enviada,Comma Product,3,1500,Cond C,Nota C';

    $file = UploadedFile::fake()->createWithContent('import_comma.csv', $csvContent);

    $response = $this->actingAs($this->user)
        ->post(route('cotizaciones.import'), [
            'archivo' => $file,
        ]);

    $response->assertStatus(302);

    $cotizacion = Cotizacion::where('numero', 'COT-COMMA-01')->first();
    expect($cotizacion)->not->toBeNull();
    expect($cotizacion->detalles[0]['descripcion'])->toBe('Comma Product');
    expect((float) $cotizacion->total)->toBe(5355.0); // (3 * 1500) * 1.19
});

test('it can import cotizaciones from csv', function () {
    $csvContent = "\xEF\xBB\xBFNumero;Fecha;Vence;Cliente_Email;Estado;Item_Descripcion;Item_Cantidad;Item_Precio;Condiciones;Notas\n";
    $csvContent .= 'COT-IMP-01;2024-04-01;2024-04-15;test@cliente.com;enviada;Producto Importado;5;2000;Cond 1;Nota 1';

    $file = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

    $response = $this->actingAs($this->user)
        ->post(route('cotizaciones.import'), [
            'archivo' => $file,
        ]);

    $response->assertStatus(302);

    $cotizacion = Cotizacion::where('numero', 'COT-IMP-01')->first();
    expect($cotizacion)->not->toBeNull();
    expect($cotizacion->cliente_id)->toBe($this->cliente->id);
    expect((float) $cotizacion->total)->toBe(11900.0); // (5 * 2000) * 1.19
    expect($cotizacion->detalles)->toHaveCount(1);
    expect($cotizacion->detalles[0]['descripcion'])->toBe('Producto Importado');
});
