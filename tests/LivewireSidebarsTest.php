<?php

use App\Livewire\AppRightSidebar;
use App\Livewire\AppSidebar;
use App\Models\PublicProfile;
use App\Models\User;
use App\Models\WebSetting;
use App\Support\SidebarNavigation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(TestCase::class);

beforeEach(function () {
    Cache::forever('web_settings:latest', new WebSetting(['app_name' => 'RedCliente', 'app_logo' => '/logo.png']));
    $this->withoutVite();
    $migration = require database_path('migrations/2026_04_18_003941_create_notifications_table.php');
    $migration->up();
});

afterEach(function () {
    $migration = require database_path('migrations/2026_04_18_003941_create_notifications_table.php');
    $migration->down();
});

function sidebarUser(array $permissions = [], array $roles = []): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => 101, 'name' => 'Visitante autenticado', 'email' => 'visitante@example.com']);
    $user->setRelation('publicProfile', null);
    $user->shouldReceive('hasAnyRole')->andReturn(count(array_intersect($roles, ['Master', 'Super Admin'])) > 0);
    $user->shouldReceive('getAllPermissions')->andReturn(collect(array_map(fn (string $name): object => (object) ['name' => $name], $permissions)));

    return $user;
}

test('los dos laterales funcionan para invitados', function () {
    Livewire::test(AppSidebar::class)->assertSee('Marketplace')->assertDontSee('Gestión Comercial');
    Livewire::test(AppRightSidebar::class)->assertSee('Ingresar')->assertDontSee('Cerrar sesión');
});

test('el izquierdo filtra los submenus por los permisos del visitante', function () {
    $this->actingAs(sidebarUser(['comercial.productos.viewAny']));

    Livewire::test(AppSidebar::class)
        ->assertSee('Gestión Comercial')->assertSee('Productos')->assertSee('Fundamental')
        ->assertDontSee('Clientes')->assertDontSee('CRM & Ventas')->assertDontSee('Gestión Humana');
});

test('el derecho muestra la identidad autenticada y filtra las acciones', function () {
    $user = sidebarUser();
    $user->setRelation('publicProfile', new PublicProfile(['slug' => 'mi-tienda', 'is_active' => true]));
    $this->actingAs($user);

    Livewire::test(AppRightSidebar::class)
        ->assertSee('Visitante autenticado')->assertSee('visitante@example.com')
        ->assertSee('Perfil Social')->assertSee('Marketplace')->assertSee('Recompensas')
        ->assertSee('Mensajes del Chat')->assertSee('Notificaciones sociales')
        ->assertDontSee('Ver mi Perfil Público')
        ->assertDontSee('Gestión SII')->assertDontSee('Conexiones API')->assertDontSee('Automatiza tu negocio');
});

test('master conserva acceso a todos los grupos del menu izquierdo', function () {
    $user = sidebarUser([], ['Master']);
    $items = SidebarNavigation::filter(config('sidebar-navigation'), $user);

    expect($items)->toHaveCount(count(config('sidebar-navigation')));
    expect(SidebarNavigation::allows($user, 'admin.web-settings.viewAny'))->toBeTrue();
});

test('el layout monta los componentes y los controles para movil', function () {
    $this->blade('<x-app-layout>Contenido de la tienda</x-app-layout>')
        ->assertSee('Navegación principal')->assertSee('Mi cuenta')
        ->assertSee('Abrir navegación')->assertSee('Abrir mi cuenta')->assertSee('Contenido de la tienda');
});

test('las acciones de notificaciones requieren una sesion', function () {
    Livewire::test(AppRightSidebar::class)->call('markNotificationsAsRead')->assertForbidden();
    Livewire::test(AppRightSidebar::class)->call('deleteNotification', (string) Str::uuid())->assertForbidden();
});

test('las acciones solo modifican notificaciones del usuario actual', function () {
    $user = sidebarUser();
    $this->actingAs($user);
    $ownId = (string) Str::uuid();
    $otherId = (string) Str::uuid();

    foreach ([$ownId => $user->id, $otherId => 202] as $id => $userId) {
        DB::table('notifications')->insert([
            'id' => $id, 'type' => 'ExampleNotification',
            'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $userId,
            'data' => json_encode(['tipo' => 'comentario', 'message' => 'Mensaje privado']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    expect(fn () => Livewire::test(AppRightSidebar::class)->call('deleteNotification', $otherId))
        ->toThrow(ModelNotFoundException::class);
    $this->assertDatabaseHas('notifications', ['id' => $otherId, 'read_at' => null]);

    Livewire::test(AppRightSidebar::class)->call('markNotificationsAsRead')->assertSuccessful();
    expect(DB::table('notifications')->where('id', $ownId)->value('read_at'))->not->toBeNull();
    $this->assertDatabaseHas('notifications', ['id' => $otherId, 'read_at' => null]);

    Livewire::test(AppRightSidebar::class)->call('deleteNotification', $ownId)->assertSuccessful();
    $this->assertDatabaseMissing('notifications', ['id' => $ownId]);
});
