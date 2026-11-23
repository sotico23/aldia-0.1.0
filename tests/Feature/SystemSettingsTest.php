<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Livewire\SystemSettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

test('guarda valores sensibles cifrados y los descifra al leerlos', function () {
    SystemSettings::save([
        'MAIL_PASSWORD' => [
            'value' => 'super-secret-smtp',
            'secret' => true,
            'group' => 'mail',
        ],
        'MAIL_HOST' => [
            'value' => 'smtp.example.com',
            'secret' => false,
            'group' => 'mail',
        ],
    ]);

    $setting = Setting::query()->where('key', 'MAIL_PASSWORD')->firstOrFail();

    expect($setting->is_encrypted)->toBeTrue()
        ->and($setting->value)->toBe('super-secret-smtp');

    $raw = DB::table('settings')->where('key', 'MAIL_PASSWORD')->value('value');

    expect($raw)->not->toBe('super-secret-smtp')
        ->and(SystemSettings::get('MAIL_PASSWORD'))->toBe('super-secret-smtp')
        ->and(SystemSettings::get('MAIL_HOST'))->toBe('smtp.example.com');
});

test('resuelve la clave de Google Maps desde la base de datos o la configuracion', function () {
    config([
        'services.frontend.google_maps_api_key' => 'frontend-env-key',
    ]);

    expect(SystemSettings::effectiveValue('VITE_GOOGLE_MAPS_API_KEY'))->toBe('frontend-env-key');

    SystemSettings::save([
        'VITE_GOOGLE_MAPS_API_KEY' => ['value' => 'frontend-db-key', 'secret' => true, 'group' => 'app'],
    ]);

    expect(SystemSettings::effectiveValue('VITE_GOOGLE_MAPS_API_KEY'))->toBe('frontend-db-key');
});

test('cifra la clave Maps y la conserva al guardar campos vacios', function () {
    SystemSettings::save([
        'VITE_GOOGLE_MAPS_API_KEY' => ['value' => 'frontend-public-key', 'secret' => true, 'group' => 'app'],
    ]);

    $setting = Setting::query()->where('key', 'VITE_GOOGLE_MAPS_API_KEY')->firstOrFail();
    $raw = DB::table('settings')->where('key', 'VITE_GOOGLE_MAPS_API_KEY')->value('value');

    expect($setting->is_encrypted)->toBeTrue()
        ->and($raw)->not->toBe('frontend-public-key');

    Livewire::test(SystemSettingsPage::class)
        ->set('secrets.VITE_GOOGLE_MAPS_API_KEY', '')
        ->call('save', 'app')
        ->assertHasNoErrors();

    expect(SystemSettings::effectiveValue('VITE_GOOGLE_MAPS_API_KEY'))->toBe('frontend-public-key');
});

test('solo comparte la clave Maps publica con el navegador', function () {
    SystemSettings::save([
        'GOOGLE_MAPS_API_KEY' => ['value' => 'server-only-secret', 'secret' => true, 'group' => 'app'],
        'VITE_GOOGLE_MAPS_API_KEY' => ['value' => 'browser-public-key', 'secret' => true, 'group' => 'app'],
    ]);

    $request = Request::create('/');
    $request->setLaravelSession(app('session')->driver());
    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['client_config']['google_maps_api_key'])->toBe('browser-public-key')
        ->and(json_encode($shared))->not->toContain('server-only-secret');
});

test('la tienda recibe la clave de navegador vigente en la base de datos', function (string $override) {
    config([
        'services.frontend.google_maps_api_key' => 'old-browser-key',
    ]);

    SystemSettings::save([
        SystemSettings::DB_OVERRIDE_KEY => ['value' => $override, 'group' => 'database'],
        'GOOGLE_MAPS_API_KEY' => ['value' => 'server-db-key', 'secret' => true, 'group' => 'app'],
        'VITE_GOOGLE_MAPS_API_KEY' => ['value' => 'current-browser-key', 'secret' => true, 'group' => 'app'],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('public-profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/public-profile')
            ->where('client_config.google_maps_api_key', 'current-browser-key')
        )
        ->assertDontSee('server-db-key');
})->with(['0', '1']);

test('usa la clave del navegador aunque coincida con un ajuste antiguo', function () {
    SystemSettings::save([
        'GOOGLE_MAPS_API_KEY' => ['value' => 'same-key', 'secret' => true, 'group' => 'app'],
        'VITE_GOOGLE_MAPS_API_KEY' => ['value' => 'same-key', 'secret' => true, 'group' => 'app'],
    ]);

    $request = Request::create('/');
    $request->setLaravelSession(app('session')->driver());
    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['client_config']['google_maps_api_key'])->toBe('same-key');
});

test('inyecta los valores almacenados en la configuracion de laravel', function () {
    SystemSettings::save([
        'VITE_APP_NAME' => ['value' => 'Mi ERP', 'group' => 'app'],
        'MAIL_MAILER' => ['value' => 'smtp', 'group' => 'mail'],
        'MAIL_HOST' => ['value' => 'smtp.example.com', 'group' => 'mail'],
        'MAIL_PASSWORD' => ['value' => 'smtp-pass', 'secret' => true, 'group' => 'mail'],
        'MAIL_FROM_ADDRESS' => ['value' => 'no-reply@example.com', 'group' => 'mail'],
        'REDIS_HOST' => ['value' => 'redis.internal', 'group' => 'redis'],
        'REVERB_HOST' => ['value' => 'ws.example.com', 'group' => 'reverb'],
        'REVERB_SCHEME' => ['value' => 'http', 'group' => 'reverb'],
        'REVERB_APP_SECRET' => ['value' => 'reverb-secret', 'secret' => true, 'group' => 'reverb'],
    ]);

    SystemSettings::applyToConfig();

    expect(config('app.name'))->toBe('Mi ERP')
        ->and(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.password'))->toBe('smtp-pass')
        ->and(config('mail.from.address'))->toBe('no-reply@example.com')
        ->and(config('database.redis.default.host'))->toBe('redis.internal')
        ->and(config('database.redis.cache.host'))->toBe('redis.internal')
        ->and(config('broadcasting.connections.reverb.options.host'))->toBe('ws.example.com')
        ->and(config('broadcasting.connections.reverb.options.scheme'))->toBe('http')
        ->and(config('broadcasting.connections.reverb.options.useTLS'))->toBeFalse()
        ->and(config('broadcasting.connections.reverb.secret'))->toBe('reverb-secret');
});

test('solo inyecta credenciales de base de datos cuando el override esta activo', function () {
    SystemSettings::save([
        'DB_CONNECTION' => ['value' => 'mysql', 'group' => 'database'],
        'DB_HOST' => ['value' => 'db.example.com', 'group' => 'database'],
        'DB_PORT' => ['value' => '3307', 'group' => 'database'],
        'DB_DATABASE' => ['value' => 'produccion', 'group' => 'database'],
        'DB_USERNAME' => ['value' => 'app_user', 'group' => 'database'],
        'DB_PASSWORD' => ['value' => 'db-secret', 'secret' => true, 'group' => 'database'],
        SystemSettings::DB_OVERRIDE_KEY => ['value' => '0', 'group' => 'database'],
    ]);

    SystemSettings::applyToConfig();

    expect(config('database.connections.mysql.host'))->not->toBe('db.example.com');

    SystemSettings::save([
        SystemSettings::DB_OVERRIDE_KEY => ['value' => '1', 'group' => 'database'],
    ]);

    SystemSettings::applyToConfig();

    expect(config('database.default'))->toBe('mysql')
        ->and(config('database.connections.mysql.host'))->toBe('db.example.com')
        ->and(config('database.connections.mysql.port'))->toBe('3307')
        ->and(config('database.connections.mysql.database'))->toBe('produccion')
        ->and(config('database.connections.mysql.username'))->toBe('app_user')
        ->and(config('database.connections.mysql.password'))->toBe('db-secret');

    // Restore the test connection so the remaining suite keeps using SQLite.
    config(['database.default' => 'sqlite']);
});

test('el componente livewire guarda valores y nunca expone secretos al navegador', function () {
    Livewire::test(SystemSettingsPage::class)
        ->set('values.VITE_APP_NAME', 'Panel Seguro')
        ->set('values.MAIL_HOST', 'smtp.secure.test')
        ->set('secrets.MAIL_PASSWORD', 'secreto-livewire')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('secrets.MAIL_PASSWORD', '')
        ->assertSet('hasStoredSecret.MAIL_PASSWORD', true)
        ->assertDontSee('secreto-livewire');

    expect(SystemSettings::get('VITE_APP_NAME'))->toBe('Panel Seguro')
        ->and(SystemSettings::get('MAIL_HOST'))->toBe('smtp.secure.test')
        ->and(SystemSettings::get('MAIL_PASSWORD'))->toBe('secreto-livewire');
});

test('el componente livewire conserva el secreto cuando el campo se envia vacio', function () {
    SystemSettings::save([
        'MAIL_PASSWORD' => [
            'value' => 'secreto-existente',
            'secret' => true,
            'group' => 'mail',
        ],
    ]);

    Livewire::test(SystemSettingsPage::class)
        ->set('values.MAIL_HOST', 'smtp.otro.test')
        ->set('secrets.MAIL_PASSWORD', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(SystemSettings::get('MAIL_PASSWORD'))->toBe('secreto-existente')
        ->and(SystemSettings::get('MAIL_HOST'))->toBe('smtp.otro.test');
});

test('la pagina de ajustes del servidor responde para usuarios autorizados', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);

    foreach (['admin.web-settings.viewAny', 'admin.web-settings.edit'] as $permission) {
        $user->givePermissionTo(
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web'])
        );
    }

    $this->actingAs($user)
        ->get('/configuracion-web/servidor')
        ->assertOk()
        ->assertSee('Ajustes del Servidor y Base de Datos')
        ->assertSee('Guardar Configuración');
});

test('el componente livewire permite eliminar un secreto almacenado', function () {
    SystemSettings::save([
        'MAIL_PASSWORD' => [
            'value' => 'secreto-a-eliminar',
            'secret' => true,
            'group' => 'mail',
        ],
    ]);

    Livewire::test(SystemSettingsPage::class)
        ->call('clearSecret', 'MAIL_PASSWORD')
        ->assertSet('hasStoredSecret.MAIL_PASSWORD', false);

    expect(Setting::query()->where('key', 'MAIL_PASSWORD')->exists())->toBeFalse()
        ->and(SystemSettings::get('MAIL_PASSWORD'))->toBeNull();
});

test('guardar una sección ignora cambios y errores de las otras secciones', function () {
    Livewire::test(SystemSettingsPage::class)
        ->set('databaseOverrideEnabled', true)
        ->set('values.DB_DATABASE', '')
        ->set('values.REDIS_PORT', 'invalido')
        ->set('values.MAIL_HOST', 'smtp.nuevo.test')
        ->set('secrets.MAIL_PASSWORD', 'clave-nueva')
        ->set('secrets.OPENROUTER_API_KEY', 'borrador')
        ->call('save', 'mail')
        ->assertHasNoErrors()
        ->assertSee('Configuración guardada correctamente.')
        ->assertSet('secrets.MAIL_PASSWORD', '')
        ->assertSet('secrets.OPENROUTER_API_KEY', 'borrador');

    expect(SystemSettings::get('MAIL_HOST'))->toBe('smtp.nuevo.test')
        ->and(SystemSettings::get('MAIL_PASSWORD'))->toBe('clave-nueva')
        ->and(SystemSettings::get('OPENROUTER_API_KEY'))->toBeNull()
        ->and(SystemSettings::get('REDIS_PORT'))->toBeNull()
        ->and(SystemSettings::get(SystemSettings::DB_OVERRIDE_KEY))->toBeNull();
});

test('probar OpenRouter usa la clave ingresada sin guardarla', function () {
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'prueba']])]);

    Livewire::test(SystemSettingsPage::class)
        ->set('secrets.OPENROUTER_API_KEY', 'clave-temporal')
        ->call('checkConnection', 'openrouter')
        ->assertHasNoErrors()
        ->assertSee('OpenRouter aceptó la clave de API.');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer clave-temporal'));
    expect(SystemSettings::get('OPENROUTER_API_KEY'))->toBeNull();
});

test('probar OpenRouter muestra un error local si el proveedor rechaza la clave', function () {
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['error' => 'rechazada'], 401)]);

    Livewire::test(SystemSettingsPage::class)
        ->set('secrets.OPENROUTER_API_KEY', 'clave-invalida')
        ->call('checkConnection', 'openrouter')
        ->assertHasErrors('connection.openrouter')
        ->assertSee('No se pudo verificar la conexión.');
});

test('probar SQLite comprueba que la base exista y permite una conexión en memoria', function () {
    Livewire::test(SystemSettingsPage::class)
        ->set('values.DB_CONNECTION', 'sqlite')
        ->set('values.DB_DATABASE', 'base-para-prueba-inexistente.sqlite')
        ->call('checkConnection', 'database')
        ->assertHasErrors('database')
        ->set('values.DB_DATABASE', ':memory:')
        ->call('checkConnection', 'database')
        ->assertHasNoErrors()
        ->assertSee('Conexión a la base de datos verificada.');
});

test('cada sección incluye los dos botones de configuración', function () {
    $settings = Livewire::test(SystemSettingsPage::class);

    foreach (array_keys(SystemSettings::groups()) as $group) {
        $settings->assertSeeHtml("wire:submit=\"save('{$group}')\"");

        if ($group !== 'app') {
            $settings->assertSeeHtml("wire:click=\"checkConnection('{$group}')\"");
        }
    }
});

test('la prueba de Maps usa la clave vigente en el navegador', function () {
    SystemSettings::save([
        'GOOGLE_MAPS_API_KEY' => ['value' => 'legacy-server-key', 'secret' => true, 'group' => 'app'],
        'VITE_GOOGLE_MAPS_API_KEY' => ['value' => 'browser-key', 'secret' => true, 'group' => 'app'],
    ]);

    Http::fake();

    Livewire::test(SystemSettingsPage::class)
        ->assertSeeHtml('data-google-maps-test="browser-key"')
        ->assertDontSee('legacy-server-key')
        ->call('checkConnection', 'app')
        ->assertStatus(404);

    Http::assertNothingSent();
});
