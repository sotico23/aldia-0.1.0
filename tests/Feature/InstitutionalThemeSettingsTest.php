<?php

use App\Models\User;
use App\Models\WebSetting;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Permission::firstOrCreate(['name' => 'admin.web-settings.viewAny', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'admin.web-settings.edit', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web']);

    $this->admin = User::factory()->create(['creator_id' => null]);
    $this->admin->assignRole('Master');
    $this->admin->givePermissionTo([
        'admin.web-settings.viewAny',
        'admin.web-settings.edit',
    ]);

    WebSetting::clearCache();
    $this->settings = WebSetting::factory()->create();
});

test('institutional theme colors can be saved', function () {
    $this->actingAs($this->admin)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->put("/configuracion-web/{$this->settings->id}", [
            'app_name' => 'RedCliente',
            'app_title' => 'RedCliente ERP',
            'timezone' => 'America/Lima',
            'locale' => 'es',
            'currency' => 'PEN',
            'currency_symbol' => 'S/',
            'theme_preset' => 'custom',
            'primary_color' => '#047857',
            'secondary_color' => '#10B981',
            'accent_color' => '#34D399',
        ])
        ->assertRedirect('/configuracion-web');

    $this->settings->refresh();

    expect($this->settings->theme_preset)->toBe('custom')
        ->and($this->settings->themeColors())->toBe([
            'primary' => '#047857',
            'secondary' => '#10B981',
            'accent' => '#34D399',
        ]);
});

test('invalid theme colors are rejected', function () {
    $this->actingAs($this->admin)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->put("/configuracion-web/{$this->settings->id}", [
            'app_name' => 'RedCliente',
            'app_title' => 'RedCliente ERP',
            'timezone' => 'America/Lima',
            'locale' => 'es',
            'currency' => 'PEN',
            'currency_symbol' => 'S/',
            'primary_color' => 'not-a-color',
        ])
        ->assertSessionHasErrors('primary_color');
});

test('theme colors fall back to the selected preset', function () {
    $this->settings->update([
        'theme_preset' => 'graphite',
        'primary_color' => null,
        'secondary_color' => null,
        'accent_color' => null,
    ]);

    expect($this->settings->themeColors())->toBe([
        'primary' => '#334155',
        'secondary' => '#64748B',
        'accent' => '#94A3B8',
    ]);
});

test('theme colors are shared with inertia responses', function () {
    $this->settings->update([
        'theme_preset' => 'graphite',
        'primary_color' => '#334155',
        'secondary_color' => '#64748B',
        'accent_color' => '#94A3B8',
    ]);

    WebSetting::clearCache();

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('web_settings.primary_color', '#334155')
            ->where('web_settings.secondary_color', '#64748B')
            ->where('web_settings.accent_color', '#94A3B8')
        );
});
