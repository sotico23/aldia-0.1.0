<?php

use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Repartidor', 'guard_name' => 'web']);

    // Absorbe el rol de Super Admin para que los demas usuarios queden bajos.
    User::factory()->create(['email' => 'dummy-setup@test.local']);

    $this->owner = User::factory()->create();

    $this->repartidorUser = User::factory()->create([
        'creator_id' => $this->owner->id,
        'has_explicit_role' => true,
    ]);
    $this->repartidorUser->assignRole('Repartidor');

    $this->repartidor = Repartidor::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->repartidorUser->id,
        'comision_pct' => 80,
        'approval_status' => Repartidor::APPROVAL_APPROVED,
    ]);

    $this->actingAsRepartidor = function () {
        Sanctum::actingAs($this->repartidorUser);
    };
});

test('earnings requiere autenticacion', function () {
    $this->getJson('/api/v1/driver/earnings')->assertStatus(401);
});

test('earnings resume las entregas del periodo', function () {
    Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'repartidor_id' => $this->repartidorUser->id,
        'estado' => 'entregado',
        'hora_entregado' => now(),
        'costo_envio' => 5000,
        'ganancia_repartidor' => 4000,
        'distancia_km' => 3,
    ]);

    // Pedido aun no entregado: no debe sumar.
    Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'repartidor_id' => $this->repartidorUser->id,
        'estado' => 'enviado',
        'costo_envio' => 9000,
    ]);

    ($this->actingAsRepartidor)();

    $this->getJson('/api/v1/driver/earnings?dias=7')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('resumen.total_ganado', 4000)
        ->assertJsonPath('resumen.total_envios', 5000)
        ->assertJsonPath('resumen.total_entregas', 1)
        ->assertJsonPath('resumen.hoy', 4000)
        ->assertJsonCount(7, 'por_dia')
        ->assertJsonCount(1, 'ultimas_entregas')
        ->assertJsonPath('ultimas_entregas.0.ganancia', 4000);
});

test('earnings estima la ganancia si el pedido no la tiene congelada', function () {
    Pedido::factory()->create([
        'owner_id' => $this->owner->id,
        'user_id' => $this->owner->id,
        'repartidor_id' => $this->repartidorUser->id,
        'estado' => 'entregado',
        'hora_entregado' => now(),
        'costo_envio' => 5000,
        'ganancia_repartidor' => null,
    ]);

    ($this->actingAsRepartidor)();

    $this->getJson('/api/v1/driver/earnings')
        ->assertOk()
        ->assertJsonPath('resumen.total_ganado', 4000);
});

test('documents lista el legajo del repartidor', function () {
    ($this->actingAsRepartidor)();

    $this->repartidor->update([
        'documentos' => [
            'licencia' => [
                'tipo' => 'licencia',
                'nombre' => 'licencia.pdf',
                'path' => 'driver-documents/1/licencia.pdf',
                'subido_at' => now()->toISOString(),
            ],
        ],
    ]);

    $this->getJson('/api/v1/driver/documents')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'documents')
        ->assertJsonPath('documents.0.tipo', 'licencia')
        ->assertJsonPath('documents.0.nombre', 'licencia.pdf');
});

test('documents sube un archivo y devuelve su url publica', function () {
    Storage::fake('public');
    ($this->actingAsRepartidor)();

    $archivo = UploadedFile::fake()->image('cedula-frontal.jpg');

    $response = $this->postJson('/api/v1/driver/documents', [
        'tipo' => 'cedula_frontal',
        'archivo' => $archivo,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.tipo', 'cedula_frontal')
        ->assertJsonPath('data.nombre', 'cedula-frontal.jpg');

    $path = $response->json('data.path');
    expect($path)->not->toBeEmpty();
    Storage::disk('public')->assertExists($path);

    $this->repartidor->refresh();
    expect($this->repartidor->documentos)->toHaveKey('cedula_frontal');
});

test('documents valida el tipo y el formato del archivo', function () {
    ($this->actingAsRepartidor)();

    $this->postJson('/api/v1/driver/documents', [
        'tipo' => 'inexistente',
        'archivo' => UploadedFile::fake()->image('foto.jpg'),
    ])->assertStatus(422)->assertJsonValidationErrors('tipo');

    $this->postJson('/api/v1/driver/documents', [
        'tipo' => 'licencia',
        'archivo' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
    ])->assertStatus(422)->assertJsonValidationErrors('archivo');
});

test('un documento nuevo devuelve el legajo rechazado a revision', function () {
    Storage::fake('public');
    ($this->actingAsRepartidor)();

    $this->repartidor->update([
        'approval_status' => Repartidor::APPROVAL_REJECTED,
        'rejected_reason' => 'Documento ilegible',
    ]);

    $this->postJson('/api/v1/driver/documents', [
        'tipo' => 'licencia',
        'archivo' => UploadedFile::fake()->image('licencia.jpg'),
    ])->assertOk();

    $this->repartidor->refresh();
    expect($this->repartidor->approval_status)->toBe(Repartidor::APPROVAL_PENDING);
    expect($this->repartidor->rejected_reason)->toBeNull();
});
