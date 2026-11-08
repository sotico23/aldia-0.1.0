<?php

use App\Livewire\SystemSettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\DB;
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
