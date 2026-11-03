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

test('the tenant owner cannot be deleted without transferring ownership', function () {
    $owner = User::factory()->create([
        'email' => 'tenant-owner@example.test',
        'has_explicit_role' => true,
    ]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $owner), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHasErrors([
            'transfer_to_user_id' => 'No puedes eliminar al propietario del negocio. Transfiere la propiedad antes de continuar.',
        ]);

    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

test('an owner role user cannot be deleted without transferring ownership', function () {
    $ownerRole = Role::firstOrCreate(
        ['name' => 'Owner', 'guard_name' => 'web'],
        ['owner_id' => null],
    );
    $ownerRole->update(['level' => 3]);

    $owner = User::factory()->create([
        'email' => 'owner-role@example.test',
        'has_explicit_role' => true,
    ]);
    $owner->assignRole('Owner');

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $owner), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHasErrors([
            'transfer_to_user_id' => 'No puedes eliminar al propietario del negocio. Transfiere la propiedad antes de continuar.',
        ]);

    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

test('the owner cannot be transferred to a user from another business', function () {
    $ownerRole = Role::firstOrCreate(
        ['name' => 'Owner', 'guard_name' => 'web'],
        ['owner_id' => null],
    );
    $ownerRole->update(['level' => 3]);

    $owner = User::factory()->create([
        'email' => 'owner-other-tenant@example.test',
        'has_explicit_role' => true,
    ]);
    $owner->assignRole('Owner');

    $otherOwner = User::factory()->create([
        'email' => 'other-tenant-owner@example.test',
        'has_explicit_role' => true,
    ]);

    $otherTenantUser = managedUserForDelete($otherOwner, [
        'email' => 'other-tenant-user@example.test',
    ]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $owner), deleteUserPayload([
            'transfer_to_user_id' => $otherTenantUser->id,
        ]))
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHasErrors([
            'transfer_to_user_id' => 'No puedes eliminar al propietario del negocio. Transfiere la propiedad antes de continuar.',
        ]);

    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

test('the owner can be deleted after transferring the business to another user', function () {
    $ownerRole = Role::firstOrCreate(
        ['name' => 'Owner', 'guard_name' => 'web'],
        ['owner_id' => null],
    );
    $ownerRole->update(['level' => 3]);

    $owner = User::factory()->create([
        'email' => 'owner-to-transfer@example.test',
        'has_explicit_role' => true,
    ]);
    $owner->assignRole('Owner');

    $successor = User::factory()->create([
        'email' => 'owner-successor@example.test',
        'owner_id' => $owner->id,
        'creator_id' => $owner->id,
        'has_explicit_role' => true,
    ]);
    $successor->assignRole('Usuario');

    $customRole = Role::create([
        'name' => 'Rol del negocio',
        'guard_name' => 'web',
        'owner_id' => $owner->id,
        'level' => 3,
    ]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $owner), deleteUserPayload([
            'transfer_to_user_id' => $successor->id,
        ]))
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $owner->id]);
    $this->assertDatabaseHas('users', [
        'id' => $successor->id,
        'owner_id' => null,
        'creator_id' => null,
    ]);
    $this->assertDatabaseHas('roles', [
        'id' => $customRole->id,
        'owner_id' => $successor->id,
    ]);
    expect($successor->fresh()->hasRole('Owner'))->toBeTrue();
});

test('an administrator with delete permission cannot delete users', function () {
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
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('a super admin cannot delete users without master or control total permission', function () {
    $superAdminRole = Role::firstOrCreate(
        ['name' => 'Super Admin', 'guard_name' => 'web'],
        ['owner_id' => null],
    );
    $superAdminRole->update(['level' => 1]);

    $superAdmin = User::factory()->create([
        'email' => 'super-admin-delete@example.test',
        'has_explicit_role' => true,
    ]);
    $superAdmin->assignRole('Super Admin');

    $target = managedUserForDelete($this->master, [
        'email' => 'super-admin-delete-target@example.test',
    ]);
    $target->assignRole('Usuario');

    $this->actingAs($superAdmin)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload())
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('a user with control total permission can delete a lower level user', function () {
    $adminRole = Role::firstOrCreate(
        ['name' => 'Administrador', 'guard_name' => 'web'],
    );
    $adminRole->update(['level' => 2]);

    $admin = managedUserForDelete($this->master, ['email' => 'control-total-admin@example.test']);
    $admin->assignRole('Administrador');

    Permission::firstOrCreate(['name' => 'admin.usuarios.viewAny', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'control.total', 'guard_name' => 'web']);
    $admin->givePermissionTo(['admin.usuarios.viewAny', 'control.total']);

    $target = managedUserForDelete($this->master, [
        'email' => 'control-total-target@example.test',
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
