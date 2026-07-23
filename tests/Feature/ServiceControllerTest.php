<?php

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;

test('guests cannot access services page', function () {
    $response = $this->get(route('services.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit services page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('services.index'));
    $response->assertOk();
});

test('services page shows created services', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $categoria = Categoria::factory()->create([
        'user_id' => $user->id,
        'tipo' => 'servicio',
        'mostrar_en_perfil' => true,
    ]);

    $service = Producto::factory()->service()->create([
        'user_id' => $user->id,
        'categoria_id' => $categoria->id,
        'peso_base' => 0,
    ]);

    $response = $this->get(route('services.index'));
    $response->assertOk();
    $this->assertDatabaseHas('productos', ['id' => $service->id, 'nombre' => $service->nombre]);
});

test('services index includes providers', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $categoria = Categoria::factory()->create([
        'user_id' => $user->id,
        'tipo' => 'servicio',
        'mostrar_en_perfil' => true,
    ]);

    $provider = User::factory()->create();

    $service = Producto::factory()->service()->create([
        'user_id' => $user->id,
        'categoria_id' => $categoria->id,
        'peso_base' => 0,
    ]);

    $service->providers()->sync([$provider->id]);

    $response = $this->get(route('services.index'));
    $response->assertOk();
    $this->assertDatabaseHas('service_provider', [
        'service_id' => $service->id,
        'user_id' => $provider->id,
    ]);
});

test('users can create a service with provider_ids', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $categoria = Categoria::factory()->create([
        'user_id' => $user->id,
        'tipo' => 'servicio',
        'mostrar_en_perfil' => true,
    ]);

    $provider = User::factory()->create();

    $this->withoutMiddleware(VerifyCsrfToken::class)
        ->post(route('services.store'), [
            'nombre' => 'Corte de Prueba',
            'descripcion' => 'Test description',
            'duracion' => 45,
            'precio_venta' => 19990,
            'categoria_id' => $categoria->id,
            'activo' => true,
            'requires_appointment' => true,
            'provider_ids' => [$provider->id],
            'imagen' => UploadedFile::fake()->image('test.jpg'),
        ]);

    $this->assertDatabaseHas('productos', [
        'nombre' => 'Corte de Prueba',
        'is_service' => true,
    ]);

    $service = Producto::where('nombre', 'Corte de Prueba')->first();
    $this->assertDatabaseHas('service_provider', [
        'service_id' => $service->id,
        'user_id' => $provider->id,
    ]);
});

test('users can update a service to change provider_ids', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $categoria = Categoria::factory()->create([
        'user_id' => $user->id,
        'tipo' => 'servicio',
        'mostrar_en_perfil' => true,
    ]);

    $provider1 = User::factory()->create();
    $provider2 = User::factory()->create();

    $service = Producto::factory()->service()->create([
        'user_id' => $user->id,
        'categoria_id' => $categoria->id,
        'peso_base' => 0,
    ]);

    $service->providers()->sync([$provider1->id]);

    $this->withoutMiddleware(VerifyCsrfToken::class)
        ->put(route('services.update', $service), [
            'nombre' => $service->nombre,
            'duracion' => $service->duracion,
            'precio_venta' => $service->precio_venta,
            'categoria_id' => $categoria->id,
            'activo' => true,
            'requires_appointment' => true,
            'provider_ids' => [$provider2->id],
        ]);

    $this->assertDatabaseMissing('service_provider', [
        'service_id' => $service->id,
        'user_id' => $provider1->id,
    ]);

    $this->assertDatabaseHas('service_provider', [
        'service_id' => $service->id,
        'user_id' => $provider2->id,
    ]);
});

test('users can remove all providers from a service', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $categoria = Categoria::factory()->create([
        'user_id' => $user->id,
        'tipo' => 'servicio',
        'mostrar_en_perfil' => true,
    ]);

    $provider = User::factory()->create();

    $service = Producto::factory()->service()->create([
        'user_id' => $user->id,
        'categoria_id' => $categoria->id,
        'peso_base' => 0,
    ]);

    $service->providers()->sync([$provider->id]);

    $this->withoutMiddleware(VerifyCsrfToken::class)
        ->put(route('services.update', $service), [
            'nombre' => $service->nombre,
            'duracion' => $service->duracion,
            'precio_venta' => $service->precio_venta,
            'categoria_id' => $categoria->id,
            'activo' => true,
            'requires_appointment' => true,
            'provider_ids' => [],
        ]);

    $this->assertDatabaseMissing('service_provider', [
        'service_id' => $service->id,
    ]);
});

test('users can delete a service and providers are detached', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $categoria = Categoria::factory()->create([
        'user_id' => $user->id,
        'tipo' => 'servicio',
        'mostrar_en_perfil' => true,
    ]);

    $provider = User::factory()->create();

    $service = Producto::factory()->service()->create([
        'user_id' => $user->id,
        'categoria_id' => $categoria->id,
        'peso_base' => 0,
    ]);

    $service->providers()->sync([$provider->id]);
    $serviceId = $service->id;

    $this->withoutMiddleware(VerifyCsrfToken::class)
        ->delete(route('services.destroy', $service));

    $this->assertDatabaseMissing('productos', ['id' => $serviceId]);
    $this->assertDatabaseMissing('service_provider', ['service_id' => $serviceId]);
});

test('service store validates required fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->withoutMiddleware(VerifyCsrfToken::class)
        ->post(route('services.store'), []);

    $response->assertInvalid(['nombre', 'duracion', 'precio_venta', 'categoria_id', 'imagen']);
});

test('service store validates provider_ids must exist in users table', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $categoria = Categoria::factory()->create([
        'user_id' => $user->id,
        'tipo' => 'servicio',
        'mostrar_en_perfil' => true,
    ]);

    $response = $this->withoutMiddleware(VerifyCsrfToken::class)
        ->post(route('services.store'), [
            'nombre' => 'Test',
            'duracion' => 30,
            'precio_venta' => 10000,
            'categoria_id' => $categoria->id,
            'activo' => true,
            'requires_appointment' => true,
            'provider_ids' => [99999],
            'imagen' => UploadedFile::fake()->image('test.jpg'),
        ]);

    $response->assertInvalid(['provider_ids.0']);
});
