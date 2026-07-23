<?php

use App\Models\PaymentConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Permission::firstOrCreate(['name' => 'admin.configuracion.viewAny', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'admin.configuracion.edit', 'guard_name' => 'web']);

    $roleMaster = Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web']);
    $roleMaster->level = 0;
    $roleMaster->save();

    Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

    // Master user with payment config
    $this->master = User::factory()->create(['name' => 'Master', 'creator_id' => null]);
    $this->master->assignRole('Master');

    $this->masterConfig = PaymentConfig::create([
        'owner_id' => $this->master->id,
        'commerce_code' => 'MASTER_COD',
        'api_key' => 'master-api-key',
        'environment' => 'integration',
        'is_active' => true,
        'paypal_client_id' => 'master-paypal',
        'paypal_client_secret' => 'master-paypal-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
        'mercadopago_public_key' => 'master-mp-pub',
        'mercadopago_access_token' => 'master-mp-token',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
        'use_platform_config' => false,
    ]);

    // Regular user without own payment config
    $this->user = User::factory()->create(['name' => 'Regular User', 'creator_id' => null]);
    $this->user->assignRole('Administrador');
    $this->user->givePermissionTo(['admin.configuracion.viewAny', 'admin.configuracion.edit']);
});

test('resolveForOwner returns own config when user has active methods and no platform toggle', function () {
    $ownConfig = PaymentConfig::create([
        'owner_id' => $this->user->id,
        'commerce_code' => 'USER_COD',
        'api_key' => 'user-api-key',
        'environment' => 'integration',
        'is_active' => true,
        'paypal_active' => false,
        'mercadopago_active' => false,
        'use_platform_config' => false,
    ]);

    $resolved = PaymentConfig::resolveForOwner($this->user->id);

    expect($resolved->id)->toBe($ownConfig->id);
    expect($resolved->commerce_code)->toBe('USER_COD');
});

test('resolveForOwner returns null when user has no active methods and no platform toggle', function () {
    PaymentConfig::create([
        'owner_id' => $this->user->id,
        'commerce_code' => 'USER_COD',
        'api_key' => 'user-api-key',
        'environment' => 'integration',
        'is_active' => false,
        'paypal_active' => false,
        'mercadopago_active' => false,
        'use_platform_config' => false,
    ]);

    $resolved = PaymentConfig::resolveForOwner($this->user->id);

    expect($resolved)->toBeNull();
});

test('resolveForOwner returns null when user has no own config at all', function () {
    $resolved = PaymentConfig::resolveForOwner($this->user->id);

    expect($resolved)->toBeNull();
});

test('resolveForOwner returns master config when user has use_platform_config enabled', function () {
    PaymentConfig::create([
        'owner_id' => $this->user->id,
        'commerce_code' => 'USER_COD',
        'api_key' => 'user-api-key',
        'environment' => 'integration',
        'is_active' => true,
        'paypal_active' => true,
        'mercadopago_active' => true,
        'use_platform_config' => true,
    ]);

    $resolved = PaymentConfig::resolveForOwner($this->user->id);

    expect($resolved->owner_id)->toBe($this->master->id);
    expect($resolved->commerce_code)->toBe('MASTER_COD');
});

test('resolveForOwner returns null for non-existent user', function () {
    $resolved = PaymentConfig::resolveForOwner(99999);

    expect($resolved)->toBeNull();
});

test('platform payment config page loads successfully', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('pagos.plataforma'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/Pagos/PlatformPaymentConfig')
    );
});

test('platform payment config toggle updates the config', function () {
    $this->actingAs($this->user);

    $ownConfig = PaymentConfig::create([
        'owner_id' => $this->user->id,
        'commerce_code' => 'USER_COD',
        'api_key' => 'user-api-key',
        'environment' => 'integration',
        'is_active' => true,
        'use_platform_config' => false,
    ]);

    $this->post(route('pagos.plataforma.update'), [
        'use_platform_config' => true,
    ]);

    $ownConfig->refresh();
    expect($ownConfig->use_platform_config)->toBeTrue();
});

test('resolveForOwner uses creator_id chain to find owner config', function () {
    // User created by Master shares Master's owner_id
    $createdUser = User::factory()->create([
        'name' => 'Created by Master',
        'creator_id' => $this->master->id,
    ]);

    // No own PaymentConfig for this user
    $resolved = PaymentConfig::resolveForOwner($createdUser->id);

    expect($resolved->owner_id)->toBe($this->master->id);
    expect($resolved->commerce_code)->toBe('MASTER_COD');
});

test('hasAnyActiveMethod returns true when any method active', function () {
    $config = new PaymentConfig([
        'is_active' => false,
        'paypal_active' => true,
        'mercadopago_active' => false,
    ]);

    expect($config->hasAnyActiveMethod())->toBeTrue();
});

test('hasAnyActiveMethod returns false when no methods active', function () {
    $config = new PaymentConfig([
        'is_active' => false,
        'paypal_active' => false,
        'mercadopago_active' => false,
    ]);

    expect($config->hasAnyActiveMethod())->toBeFalse();
});
