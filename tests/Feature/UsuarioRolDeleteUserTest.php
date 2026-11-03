<?php

use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Evita que el primer usuario del test reciba el rol Super Admin por accidente.
    User::factory()->create(['email' => 'bootstrap-delete-user@example.test']);

    $masterRole = Role::firstOrCreate(
        ['name' => 'Master', 'guard_name' => 'web'],
        ['owner_id' => null],
    );
    $masterRole->update(['level' => 0]);

    $this->master = User::factory()->create([
        'email' => 'master-delete-user@example.test',
        'has_explicit_role' => true,
    ]);
    $this->master->assignRole('Master');
});

function managedUserForDelete(User $master, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'owner_id' => $master->getOwnerId(),
        'creator_id' => $master->id,
        'has_explicit_role' => true,
    ], $attributes));
}

function deleteUserPayload(array $overrides = []): array
{
    return array_merge([
        'delete_sales' => true,
        'delete_activity_logs' => true,
        'delete_access_assignments' => true,
        'delete_profile_sessions' => true,
    ], $overrides);
}

test('master can permanently delete a user and the selected related data', function () {
    $target = managedUserForDelete($this->master, ['email' => 'delete-target@example.test']);
    $target->assignRole('Usuario');

    $permission = Permission::firstOrCreate(
        ['name' => 'ver dashboard', 'guard_name' => 'web'],
    );
    $target->givePermissionTo($permission);

    $sale = Venta::factory()->create([
        'user_id' => $target->id,
        'owner_id' => $this->master->getOwnerId(),
    ]);

    $target->createToken('delete-test-token');

    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
    $this->assertDatabaseMissing('ventas', ['id' => $sale->id]);
    $this->assertDatabaseMissing('model_has_roles', [
        'model_id' => $target->id,
        'model_type' => $target->getMorphClass(),
    ]);
    $this->assertDatabaseMissing('model_has_permissions', [
        'model_id' => $target->id,
        'model_type' => $target->getMorphClass(),
    ]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $target->id,
        'tokenable_type' => $target->getMorphClass(),
    ]);
});

test('sales can be reassigned to an archive user before the account is deleted', function () {
    $target = managedUserForDelete($this->master, ['email' => 'transfer-target@example.test']);
    $archive = managedUserForDelete($this->master, ['email' => 'archive-user@example.test']);

    $sale = Venta::factory()->create([
        'user_id' => $target->id,
        'owner_id' => $this->master->getOwnerId(),
    ]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload([
            'transfer_to_user_id' => $archive->id,
        ]))
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
    $this->assertDatabaseHas('ventas', [
        'id' => $sale->id,
        'user_id' => $archive->id,
    ]);
});

test('activity log rows are removed when the activity_logs table exists', function () {
    Schema::dropIfExists('activity_logs');
    Schema::create('activity_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
    });

    $target = managedUserForDelete($this->master, ['email' => 'activity-log-target@example.test']);
    DB::table('activity_logs')->insert(['user_id' => $target->id]);

    try {
        $this->actingAs($this->master)
            ->from(route('usuarios-roles.index'))
            ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload())
            ->assertRedirect(route('usuarios-roles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('activity_logs', ['user_id' => $target->id]);
    } finally {
        Schema::dropIfExists('activity_logs');
    }
});

test('a user cannot delete their own account', function () {
    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $this->master), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $this->master->id]);
});

test('the tenant owner cannot be deleted from the user panel', function () {
    $owner = User::factory()->create([
        'email' => 'tenant-owner@example.test',
        'has_explicit_role' => true,
    ]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $owner), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

test('an administrator with delete permission can delete a lower level user', function () {
    $adminRole = Role::firstOrCreate(
        ['name' => 'Administrador', 'guard_name' => 'web'],
    );
    $adminRole->update(['level' => 2]);

    $admin = managedUserForDelete($this->master, ['email' => 'delete-admin@example.test']);
    $admin->assignRole('Administrador');

    Permission::firstOrCreate(['name' => 'admin.usuarios.viewAny', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'admin.usuarios.delete', 'guard_name' => 'web']);
    $admin->givePermissionTo(['admin.usuarios.viewAny', 'admin.usuarios.delete']);

    $target = managedUserForDelete($this->master, [
        'email' => 'admin-delete-target@example.test',
        'creator_id' => $admin->id,
    ]);
    $target->assignRole('Usuario');

    $this->actingAs($admin)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

test('a user without delete permission cannot delete another user', function () {
    $regular = managedUserForDelete($this->master, ['email' => 'regular-user@example.test']);
    $regular->assignRole('Usuario');

    $target = managedUserForDelete($this->master, ['email' => 'protected-target@example.test']);
    $target->assignRole('Usuario');

    $this->actingAs($regular)
        ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload())
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});
