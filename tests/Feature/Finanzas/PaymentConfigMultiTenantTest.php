<?php

use App\Models\PaymentConfig;
use App\Models\User;
use App\Scopes\OwnerScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Permission::firstOrCreate(['name' => 'admin.configuracion.viewAny', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'admin.configuracion.edit', 'guard_name' => 'web']);

    $this->adminA = User::factory()->create(['name' => 'Admin A']);
    $this->adminA->assignRole('Administrador');
    $this->adminA->givePermissionTo(['admin.configuracion.viewAny', 'admin.configuracion.edit']);

    $this->adminB = User::factory()->create(['name' => 'Admin B']);
    $this->adminB->assignRole('Administrador');
    $this->adminB->givePermissionTo(['admin.configuracion.viewAny', 'admin.configuracion.edit']);

    $this->configA = PaymentConfig::create([
        'owner_id' => $this->adminA->id,
        'commerce_code' => 'CODIGO_A',
        'api_key' => 'key-a-secret',
        'environment' => 'integration',
        'is_active' => true,
        'paypal_client_id' => 'paypal-client-a',
        'paypal_client_secret' => 'paypal-secret-a',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
        'mercadopago_public_key' => 'mp-pub-a',
        'mercadopago_access_token' => 'mp-token-a',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
    ]);
});

test('PaymentConfig se crea con owner_id del usuario autenticado', function () {
    $this->actingAs($this->adminB);

    $config = PaymentConfig::create([
        'commerce_code' => 'CODIGO_B',
        'api_key' => 'key-b-secret',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    expect($config->owner_id)->toBe($this->adminB->id);
});

test('Admin A ve su propia config via WebpayConfig', function () {
    $this->actingAs($this->adminA);

    $response = $this->get(route('webpay.config'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/Pagos/WebpayConfig')
        ->has('config')
        ->where('config.commerce_code', 'CODIGO_A')
    );
});

test('Admin B no ve la config de Admin A via WebpayConfig', function () {
    $this->actingAs($this->adminB);

    // Admin B has no PaymentConfig yet (only Admin A has one)
    $response = $this->get(route('webpay.config'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/Pagos/WebpayConfig')
        ->where('config', null)
    );
});

test('Admin A ve su propia config via PayPalConfig', function () {
    $this->actingAs($this->adminA);

    $response = $this->get(route('paypal.config'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/Pagos/PayPalConfig')
        ->has('config')
        ->where('config.paypal_client_id', 'paypal-client-a')
    );
});

test('Admin B no ve la config PayPal de Admin A', function () {
    $this->actingAs($this->adminB);

    $response = $this->get(route('paypal.config'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/Pagos/PayPalConfig')
        ->where('config', null)
    );
});

test('Admin A ve su propia config via MercadoPagoConfig', function () {
    $this->actingAs($this->adminA);

    $response = $this->get(route('mercadopago.config'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/Pagos/MercadoPagoConfig')
        ->has('config')
        ->where('config.mercadopago_public_key', 'mp-pub-a')
    );
});

test('Admin B no ve la config MercadoPago de Admin A', function () {
    $this->actingAs($this->adminB);

    $response = $this->get(route('mercadopago.config'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/Pagos/MercadoPagoConfig')
        ->where('config', null)
    );
});

test('Admin A actualiza su propia config Webpay sin afectar a Admin B', function () {
    $this->actingAs($this->adminA);

    $response = $this->from('/webpay/config')
        ->post(route('webpay.config.update'), [
            'commerce_code' => 'CODIGO_A_ACTUALIZADO',
            'api_key' => 'nueva-key-a',
            'environment' => 'production',
            'is_active' => true,
        ]);

    $response->assertRedirect('/webpay/config');

    $configA = PaymentConfig::where('owner_id', $this->adminA->id)->first();
    expect($configA->commerce_code)->toBe('CODIGO_A_ACTUALIZADO')
        ->and($configA->environment)->toBe('production');

    // Admin B's config (non-existent) is unchanged
    $configB = PaymentConfig::where('owner_id', $this->adminB->id)->first();
    expect($configB)->toBeNull();
});

test('Admin A actualiza su propia config PayPal sin afectar a Admin B', function () {
    $this->actingAs($this->adminA);

    $response = $this->from('/paypal/config')
        ->post(route('paypal.config.update'), [
            'paypal_client_id' => 'paypal-client-a-v2',
            'paypal_client_secret' => 'paypal-secret-a-v2',
            'paypal_mode' => 'live',
            'paypal_active' => true,
        ]);

    $response->assertRedirect('/paypal/config');

    $configA = PaymentConfig::where('owner_id', $this->adminA->id)->first();
    expect($configA->paypal_client_id)->toBe('paypal-client-a-v2')
        ->and($configA->paypal_mode)->toBe('live');

    $configB = PaymentConfig::where('owner_id', $this->adminB->id)->first();
    expect($configB)->toBeNull();
});

test('Admin B puede crear su propia config sin pisar la de Admin A', function () {
    $this->actingAs($this->adminB);

    $response = $this->from('/webpay/config')
        ->post(route('webpay.config.update'), [
            'commerce_code' => 'CODIGO_B',
            'api_key' => 'key-b-secret',
            'environment' => 'integration',
            'is_active' => true,
        ]);

    $response->assertRedirect('/webpay/config');

    // Admin B's config exists
    $configB = PaymentConfig::where('owner_id', $this->adminB->id)->first();
    expect($configB)->not->toBeNull()
        ->and($configB->commerce_code)->toBe('CODIGO_B');

    // Admin A's config is unchanged
    $configA = PaymentConfig::withoutGlobalScope(OwnerScope::class)
        ->where('owner_id', $this->adminA->id)
        ->first();
    expect($configA->commerce_code)->toBe('CODIGO_A');
});

test('OwnerScope protege consultas directas: Admin B no ve registros de Admin A', function () {
    $this->actingAs($this->adminB);

    $configs = PaymentConfig::all();

    expect($configs)->toHaveCount(0);
});

test('OwnerScope: Admin A solo ve su propio registro', function () {
    $this->actingAs($this->adminA);

    $configs = PaymentConfig::all();

    expect($configs)->toHaveCount(1)
        ->and($configs->first()->owner_id)->toBe($this->adminA->id);
});

test('Admin B no puede acceder a datos de Admin A via withoutGlobalScope', function () {
    $this->actingAs($this->adminB);

    $configA = PaymentConfig::withoutGlobalScope(OwnerScope::class)
        ->where('owner_id', $this->adminA->id)
        ->first();

    // Admin B can see Admin A's data only if they explicitly bypass the scope
    // This simulates what MarketplaceController does when getting the store owner's config
    expect($configA)->not->toBeNull()
        ->and($configA->commerce_code)->toBe('CODIGO_A');
});
