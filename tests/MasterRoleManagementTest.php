<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleRegionalSettings;
use App\Http\Middleware\TrackPageViews;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');
    config()->set('cache.default', 'array');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('country')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->rememberToken();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->unsignedBigInteger('creator_id')->nullable();
        $table->boolean('is_active')->default(true);
        $table->boolean('has_explicit_role')->default(true);
        $table->timestamps();
    });
    foreach ([
        '2026_03_08_214259_create_permission_tables.php',
        '2026_05_13_233332_add_level_to_roles_table.php',
        '2026_05_31_210237_add_owner_id_to_roles_table.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    Schema::create('public_profiles', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->string('title');
    });
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutMiddleware([HandleInertiaRequests::class, HandleRegionalSettings::class, TrackPageViews::class]);
    $this->master = User::withoutEvents(fn (): User => User::factory()->create(['has_explicit_role' => true]));
    $masterRole = Role::firstOrCreate(['name' => 'Master', 'guard_name' => 'web'], ['level' => 0, 'owner_id' => null]);
    $masterRole->update(['level' => 0]);
    $this->master->assignRole($masterRole);
    $this->owner = User::withoutEvents(fn (): User => User::factory()->create(['has_explicit_role' => true]));
    $this->permission = Permission::firstOrCreate(['name' => 'inventario.inventarios.viewAny', 'guard_name' => 'web']);
});

function roleForMasterManagement(string $name, ?int $ownerId = null): Role
{
    $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['level' => 3]);
    $role->update(['owner_id' => $ownerId]);

    return $role;
}

test('master sees system roles and roles belonging to every business', function () {
    $cashier = roleForMasterManagement('Cajero', $this->owner->id);
    $driver = roleForMasterManagement('Repartidor', $this->owner->id);
    $this->actingAs($this->master)->withHeader('X-Inertia', 'true')
        ->get(route('usuarios-roles.index'))->assertOk()
        ->assertJsonPath('props.is_master', true)
        ->assertJsonFragment(['id' => $cashier->id, 'name' => 'Cajero'])
        ->assertJsonFragment(['id' => $driver->id, 'name' => 'Repartidor']);
});

test('master creates globally available roles with their selected permissions', function () {
    $this->actingAs($this->master)->post(route('usuarios-roles.role.store'), [
        'name' => 'Coordinador global', 'permissions' => [$this->permission->id],
    ])->assertRedirect(route('usuarios-roles.index'))->assertSessionHasNoErrors();
    $role = Role::where('name', 'Coordinador global')->firstOrFail();
    expect($role->owner_id)->toBeNull()
        ->and((int) $role->created_by)->toBe($this->master->id)
        ->and($role->hasPermissionTo($this->permission))->toBeTrue();
});

test('master edits any role regardless of its name or business owner', function (string $name, bool $businessRole) {
    $role = roleForMasterManagement($name, $businessRole ? $this->owner->id : null);
    $this->actingAs($this->master)->put(route('usuarios-roles.role.update', $role), [
        'name' => $name.' actualizado', 'permissions' => [$this->permission->id],
    ])->assertRedirect(route('usuarios-roles.index'))->assertSessionHasNoErrors();
    expect($role->fresh()->name)->toBe($name.' actualizado')
        ->and($role->fresh()->hasPermissionTo($this->permission))->toBeTrue();
})->with([
    ['Master', false], ['Super Admin', false], ['Administrador', false],
    ['Cajero', false], ['Repartidor', false], ['Cajero', true], ['Repartidor', true], ['Rol de otro negocio', true],
]);

test('master deletes any role and removes its assignments', function (string $name, bool $businessRole) {
    $role = roleForMasterManagement($name, $businessRole ? $this->owner->id : null);
    $this->owner->assignRole($role);
    $this->actingAs($this->master)->delete(route('usuarios-roles.role.destroy', $role))
        ->assertRedirect(route('usuarios-roles.index'))->assertSessionHasNoErrors();
    $this->assertModelMissing($role);
    $this->assertDatabaseMissing('model_has_roles', ['role_id' => $role->id]);
})->with([
    ['Master', false], ['Super Admin', false], ['Administrador', false],
    ['Cajero', false], ['Repartidor', false], ['Cajero', true], ['Repartidor', true], ['Rol de otro negocio', true],
]);

test('business administrators cannot edit or delete roles from another business or system roles', function (bool $systemRole) {
    $administrator = User::withoutEvents(fn (): User => User::factory()->create(['has_explicit_role' => true]));
    $administrator->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.usuarios.viewAny', 'guard_name' => 'web']));
    $administrator->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.roles.custom', 'guard_name' => 'web']));
    $role = roleForMasterManagement('Rol protegido', $systemRole ? null : $this->owner->id);
    $this->actingAs($administrator)->put(route('usuarios-roles.role.update', $role), ['name' => 'No permitido'])
        ->assertForbidden();
    $this->actingAs($administrator)->delete(route('usuarios-roles.role.destroy', $role))->assertForbidden();
    expect($role->fresh()->name)->toBe('Rol protegido');
})->with([true, false]);

test('business administrators still create roles scoped to their own business', function () {
    $administrator = User::withoutEvents(fn (): User => User::factory()->create(['has_explicit_role' => true]));
    foreach (['admin.usuarios.viewAny', 'admin.roles.custom'] as $permission) {
        $administrator->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }
    $this->actingAs($administrator)->post(route('usuarios-roles.role.store'), ['name' => 'Rol local', 'permissions' => []])
        ->assertRedirect(route('usuarios-roles.index'));
    expect((int) Role::where('name', 'Rol local')->firstOrFail()->owner_id)->toBe($administrator->getOwnerId());
});
