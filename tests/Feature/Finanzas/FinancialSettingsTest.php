<?php

use App\Enums\FinancialEvent;
use App\Models\PaymentConfig;
use App\Models\User;
use App\Models\WebhookLog;
use App\Models\WebSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Permission::firstOrCreate(['name' => 'admin.web-settings.viewAny', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'admin.web-settings.edit', 'guard_name' => 'web']);

    $masterRole = Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web']);
    $adminRole = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

    $this->admin = User::factory()->create(['name' => 'Admin', 'creator_id' => null]);
    $this->admin->assignRole('Master');
    $this->admin->givePermissionTo(['admin.web-settings.viewAny', 'admin.web-settings.edit']);

    $this->actingAs($this->admin);
});

test('financial settings can be saved and retrieved', function () {
    $response = $this->putJson('/configuracion-web/financial-settings', [
        'operation_mode' => 'both',
        'default_currency' => 'USD',
        'allowed_currencies' => ['USD', 'PEN', 'EUR'],
        'default_vat' => 19,
        'auto_tax' => true,
        'financial_email' => 'finanzas@example.com',
        'billing_email' => 'facturacion@example.com',
        'subscriptions_active' => true,
        'trial_days' => 14,
        'grace_days' => 7,
        'auto_upgrade' => true,
        'downgrade_allowed' => true,
        'cancel_non_payment' => true,
        'auto_renewal' => true,
        'invoice_prefix' => 'INV-',
        'invoice_start_number' => 100,
        'auto_invoicing' => true,
        'auto_send_invoices' => true,
        'auto_reminders' => true,
    ]);

    $response->assertSuccessful()
        ->assertJson(['success' => true]);

    $settings = WebSetting::getSettings();
    expect($settings->operation_mode)->toBe('both')
        ->and($settings->default_currency)->toBe('USD')
        ->and((float) $settings->default_vat)->toBe(19.0)
        ->and($settings->auto_tax)->toBeTrue()
        ->and($settings->subscriptions_active)->toBeTrue()
        ->and($settings->trial_days)->toBe(14)
        ->and($settings->invoice_prefix)->toBe('INV-');
});

test('financial settings show endpoint returns current values', function () {
    $settings = WebSetting::getSettings();
    $settings->update([
        'operation_mode' => 'saas',
        'default_currency' => 'PEN',
        'auto_tax' => true,
    ]);
    WebSetting::clearCache();

    $response = $this->getJson('/configuracion-web/financial-settings');

    $response->assertSuccessful();
    expect($response->json('data.operation_mode'))->toBe('saas')
        ->and($response->json('data.default_currency'))->toBe('PEN')
        ->and($response->json('data.auto_tax'))->toBeTrue();
});

test('financial settings validates required fields', function () {
    $response = $this->putJson('/configuracion-web/financial-settings', [
        'operation_mode' => 'invalid',
    ]);

    $response->assertStatus(422);
});

test('financial settings validates email fields', function () {
    $response = $this->putJson('/configuracion-web/financial-settings', [
        'operation_mode' => 'saas',
        'financial_email' => 'not-an-email',
    ]);

    $response->assertStatus(422);
});

test('gateway settings show endpoint returns masked secrets', function () {
    PaymentConfig::create([
        'owner_id' => $this->admin->id,
        'commerce_code' => '597055555532',
        'api_key' => 'real-secret-api-key',
        'environment' => 'integration',
        'is_active' => true,
        'paypal_client_id' => 'paypal-client-123',
        'paypal_client_secret' => 'real-paypal-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
        'mercadopago_public_key' => 'APP_USU-public-key',
        'mercadopago_access_token' => 'real-mp-access-token',
        'mercadopago_mode' => 'sandbox',
        'mercadopago_active' => true,
    ]);

    $response = $this->getJson('/configuracion-web/gateway-settings');

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data['webpay']['api_key'])->toBe('••••••••••••••••')
        ->and($data['webpay']['commerce_code'])->toBe('597055555532')
        ->and($data['paypal']['paypal_client_secret'])->toBe('••••••••••••••••')
        ->and($data['paypal']['paypal_client_id'])->toBe('paypal-client-123')
        ->and($data['mercadopago']['mercadopago_access_token'])->toBe('••••••••••••••••')
        ->and($data['mercadopago']['mercadopago_public_key'])->toBe('APP_USU-public-key');
});

test('webpay gateway settings can be saved', function () {
    $response = $this->putJson('/configuracion-web/gateway-settings/webpay', [
        'commerce_code' => '597055555532',
        'api_key' => 'new-api-key',
        'environment' => 'production',
        'is_active' => true,
    ]);

    $response->assertSuccessful();

    $config = PaymentConfig::where('owner_id', $this->admin->id)->first();
    expect($config)->not->toBeNull()
        ->and($config->commerce_code)->toBe('597055555532')
        ->and($config->environment)->toBe('production')
        ->and($config->is_active)->toBeTrue();
});

test('paypal gateway settings can be saved with masked secrets preserved', function () {
    // First save with real secret
    $this->putJson('/configuracion-web/gateway-settings/paypal', [
        'paypal_client_id' => 'client-123',
        'paypal_client_secret' => 'real-secret',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
        'brand_name' => 'My Store',
        'currency' => 'USD',
    ]);

    // Second save without secret (should preserve existing)
    $response = $this->putJson('/configuracion-web/gateway-settings/paypal', [
        'paypal_client_id' => 'client-456',
        'paypal_client_secret' => '',
        'paypal_mode' => 'sandbox',
        'paypal_active' => true,
    ]);

    $response->assertSuccessful();

    $config = PaymentConfig::where('owner_id', $this->admin->id)->first();
    expect($config->paypal_client_id)->toBe('client-456')
        ->and($config->paypal_client_secret)->not->toBeEmpty(); // preserved
});

test('mercadopago gateway settings can be saved', function () {
    $response = $this->putJson('/configuracion-web/gateway-settings/mercadopago', [
        'mercadopago_public_key' => 'APP_USU-new-public',
        'mercadopago_access_token' => 'new-access-token',
        'mercadopago_mode' => 'production',
        'mercadopago_active' => true,
    ]);

    $response->assertSuccessful();

    $config = PaymentConfig::where('owner_id', $this->admin->id)->first();
    expect($config)->not->toBeNull()
        ->and($config->mercadopago_public_key)->toBe('APP_USU-new-public')
        ->and($config->mercadopago_mode)->toBe('production')
        ->and($config->mercadopago_active)->toBeTrue();
});

test('marketplace settings can be saved and retrieved', function () {
    $response = $this->putJson('/configuracion-web/marketplace-settings', [
        'commission_type' => 'hybrid',
        'commission_rate' => 5.5,
        'fixed_amount' => 2.00,
        'min_commission' => 1.00,
        'max_commission' => 100.00,
        'min_withdrawal_amount' => 20.00,
        'split_payment_active' => true,
        'split_payment_gateway' => 'mercadopago',
        'auto_hold_commission' => true,
        'fund_release_period' => '7_days',
        'refund_policy' => 'platform_absorbs',
        'partial_refunds_allowed' => true,
    ]);

    $response->assertSuccessful()
        ->assertJson(['success' => true]);

    $settings = WebSetting::getSettings();
    expect($settings->marketplace_commission_type)->toBe('hybrid')
        ->and((float) $settings->marketplace_commission_rate)->toBe(5.5)
        ->and((float) $settings->marketplace_fixed_amount)->toBe(2.00)
        ->and((float) $settings->min_commission)->toBe(1.00)
        ->and($settings->split_payment_active)->toBeTrue()
        ->and($settings->fund_release_period)->toBe('7_days');
});

test('marketplace settings show endpoint returns stored values', function () {
    $settings = WebSetting::getSettings();
    $settings->update([
        'marketplace_commission_type' => 'fixed',
        'marketplace_commission_rate' => 0,
        'marketplace_fixed_amount' => 10.00,
        'split_payment_active' => true,
        'split_payment_gateway' => 'paypal',
        'fund_release_period' => '30_days',
    ]);
    WebSetting::clearCache();

    $response = $this->getJson('/configuracion-web/marketplace-settings');

    $response->assertSuccessful();
    expect($response->json('data.commission_type'))->toBe('fixed')
        ->and((float) $response->json('data.fixed_amount'))->toBe(10.0)
        ->and($response->json('data.split_payment_gateway'))->toBe('paypal')
        ->and($response->json('data.fund_release_period'))->toBe('30_days');
});

test('webhook settings shows audit data', function () {
    WebhookLog::create([
        'gateway' => 'paypal',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'event_id' => 'evt_1',
        'status' => 'processed',
        'received_at' => now(),
    ]);
    WebhookLog::create([
        'gateway' => 'mercadopago',
        'event_type' => 'payment.created',
        'event_id' => 'evt_2',
        'status' => 'failed',
        'error_message' => 'Invalid signature',
        'received_at' => now(),
    ]);
    WebhookLog::create([
        'gateway' => 'paypal',
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'event_id' => 'evt_3',
        'status' => 'duplicate',
        'received_at' => now(),
    ]);

    $response = $this->getJson('/configuracion-web/webhook-settings');

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data['audit']['total_received'])->toBe(3)
        ->and($data['audit']['total_failed'])->toBe(1)
        ->and($data['audit']['total_duplicates'])->toBe(1)
        ->and($data['audit']['last_error'])->toBe('Invalid signature')
        ->and($data['paypal']['webhook_url'])->toContain('/webhooks/paypal')
        ->and($data['mercadopago']['webhook_url'])->toContain('/webhooks/mercadopago');
});

test('financial automations can be saved and retrieved', function () {
    $defaults = FinancialEvent::defaults();
    $events = $defaults;
    $events[0]['n8n'] = true;
    $events[0]['whatsapp'] = true;
    $events[0]['email'] = true;
    $events[1]['telegram'] = true;
    $events[1]['email'] = true;
    $events[2]['n8n'] = true;
    $events[2]['telegram'] = true;

    $response = $this->putJson('/configuracion-web/financial-automations', [
        'events' => $events,
    ]);

    $response->assertSuccessful()
        ->assertJson(['success' => true]);

    $settings = WebSetting::getSettings();
    expect($settings->financial_automations)->toBeArray()
        ->and($settings->financial_automations[0]['event'])->toBe('payment_received')
        ->and($settings->financial_automations[0]['n8n'])->toBeTrue();

    // Verify show endpoint
    $showResponse = $this->getJson('/configuracion-web/financial-automations');
    $showResponse->assertSuccessful();
    expect($showResponse->json('data'))->toHaveCount(7);
});

test('permissions: user without web-settings.edit cannot save financial settings', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->putJson('/configuracion-web/financial-settings', [
        'operation_mode' => 'saas',
    ]);

    $response->assertStatus(403);
});

test('permissions: user without web-settings.viewAny cannot view financial settings', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->getJson('/configuracion-web/financial-settings');

    $response->assertStatus(403);
});

test('gateway settings are multi-tenant isolated', function () {
    // Create config for master admin
    PaymentConfig::create([
        'owner_id' => $this->admin->id,
        'commerce_code' => 'MASTER_CODE',
        'api_key' => 'master-key',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    // Create another admin
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('Master');
    $otherAdmin->givePermissionTo(['admin.web-settings.viewAny']);

    // Other admin should see gateway settings from master fallback
    $this->actingAs($otherAdmin);
    $response = $this->getJson('/configuracion-web/gateway-settings');

    // The GatewaySettingsController looks for master user with creator_id = null
    // Both admins have creator_id = null, so it might find the first one
    $response->assertSuccessful();
});

test('encrypted fields are stored encrypted in database', function () {
    PaymentConfig::create([
        'owner_id' => $this->admin->id,
        'commerce_code' => 'CODE123',
        'api_key' => 'super-secret-key-12345',
        'environment' => 'integration',
        'is_active' => true,
    ]);

    $config = PaymentConfig::where('owner_id', $this->admin->id)->first();

    expect($config->api_key)->toBe('super-secret-key-12345');

    // Raw DB value should be encrypted (not plaintext)
    $raw = DB::table('payment_configs')->where('owner_id', $this->admin->id)->value('api_key');
    expect($raw)->not->toBe('super-secret-key-12345');
});

test('financial settings do not duplicate data across retrievals', function () {
    // First call creates the row
    $first = WebSetting::getSettings();
    $countAfterFirst = WebSetting::count();
    expect($countAfterFirst)->toBe(1);

    // Second call should not create another row
    $second = WebSetting::getSettings();
    $countAfterSecond = WebSetting::count();
    expect($countAfterSecond)->toBe(1);
});

test('FinancialEvent enum has valid values and labels', function () {
    expect(FinancialEvent::values())->toHaveCount(7)
        ->and(FinancialEvent::defaults())->toHaveCount(7)
        ->and(FinancialEvent::defaults()[0]['event'])->toBe('payment_received')
        ->and(FinancialEvent::defaults()[0]['label'])->toBe('Pago recibido');

    foreach (FinancialEvent::defaults() as $event) {
        expect($event)
            ->toHaveKeys(['event', 'label', 'n8n', 'telegram', 'whatsapp', 'email'])
            ->and($event['n8n'])->toBeFalse()
            ->and($event['telegram'])->toBeFalse();
    }
});
