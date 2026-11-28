<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('only Master can save shop branding and anyone can read the published result', function () {
    Storage::fake('public');
    $path = '/api/v1/shop/branding';
    $this->getJson($path)->assertOk()->assertJsonPath('app_name', 'RedClienteShop');
    $this->postJson($path)->assertUnauthorized();

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
    $adminToken = $admin->createToken('shop')->plainTextToken;
    $this->withToken($adminToken)->postJson($path)->assertForbidden();

    $master = User::factory()->create();
    $master->assignRole(Role::findOrCreate('Master', 'web'));
    $masterToken = $master->createToken('shop')->plainTextToken;
    $this->withToken($masterToken)->postJson($path)->assertUnprocessable();
    $response = $this->withToken($masterToken)->post($path, [
        'app_name' => 'Mi tienda',
        'description' => 'Descripción pública',
        'login_description' => 'Ingresa a tu cuenta',
        'logo' => UploadedFile::fake()->image('logo.png', 32, 32),
    ], ['Accept' => 'application/json'])->assertOk();

    expect(Storage::disk('public')->files('branding/shop'))->toHaveCount(1);
    $this->flushHeaders()->getJson($path)->assertOk()->assertExactJson([
        'app_name' => 'Mi tienda',
        'description' => 'Descripción pública',
        'login_description' => 'Ingresa a tu cuenta',
        'logo_url' => $response->json('logo_url'),
    ]);
});
