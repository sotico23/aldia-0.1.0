<?php

use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
    return $overrides;
}

test('master can permanently delete a user and all related data', function () {
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

test('related data is deleted instead of transferred to another user', function () {
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
    $this->assertDatabaseMissing('ventas', ['id' => $sale->id]);
    $this->assertDatabaseHas('users', ['id' => $archive->id]);
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

test('related files are deleted from storage when the user is purged', function () {
    Storage::fake('public');

    $target = managedUserForDelete($this->master, ['email' => 'files-target@example.test']);

    Storage::disk('public')->put('profile-photos/avatar.jpg', 'avatar');
    Storage::disk('public')->put('logos/business.png', 'logo');

    $target->update([
        'profile_photo_path' => 'profile-photos/avatar.jpg',
        'business_logo_path' => 'logos/business.png',
    ]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    Storage::disk('public')->assertMissing([
        'profile-photos/avatar.jpg',
        'logos/business.png',
    ]);
});

test('tables with restrict foreign keys do not block the purge', function () {
    $target = managedUserForDelete($this->master, ['email' => 'restrict-fk-target@example.test']);

    DB::table('inventory_closures')->insert([
        'owner_id' => $this->master->getOwnerId(),
        'user_id' => $target->id,
        'closure_date' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
    $this->assertDatabaseMissing('inventory_closures', ['user_id' => $target->id]);
});

test('related tables without declared foreign keys are purged too', function () {
    $target = managedUserForDelete($this->master, ['email' => 'unconstrained-related-target@example.test']);

    DB::table('raffles')->insert([
        'owner_id' => $target->owner_id,
        'user_id' => $target->id,
        'title' => 'Sorteo de prueba',
        'slug' => Str::random(12),
        'type' => 'raffle',
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
    $this->assertDatabaseMissing('raffles', ['user_id' => $target->id]);
});

test('tokens and notifications belonging to the user are purged', function () {
    $target = managedUserForDelete($this->master, ['email' => 'tokens-notifications-target@example.test']);

    $target->createToken('purge-notification-token');

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => $target->getMorphClass(),
        'notifiable_id' => $target->id,
        'data' => '{}',
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $target->id,
        'tokenable_type' => $target->getMorphClass(),
    ]);
    $this->assertDatabaseMissing('notifications', [
        'notifiable_id' => $target->id,
        'notifiable_type' => $target->getMorphClass(),
    ]);
});

test('the whole purge is rolled back when a dependent table fails', function () {
    $target = managedUserForDelete($this->master, ['email' => 'rollback-target@example.test']);

    $sale = Venta::factory()->create([
        'user_id' => $target->id,
        'owner_id' => $this->master->getOwnerId(),
    ]);

    DB::unprepared("CREATE TRIGGER prevent_user_delete BEFORE DELETE ON users BEGIN SELECT RAISE(ABORT, 'blocked by test'); END;");

    try {
        $this->actingAs($this->master)
            ->from(route('usuarios-roles.index'))
            ->delete(route('usuarios-roles.user.destroy', $target), deleteUserPayload())
            ->assertRedirect(route('usuarios-roles.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('ventas', ['id' => $sale->id, 'user_id' => $target->id]);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_user_delete');
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

test('the tenant owner is deleted with all company users and records', function () {
    $owner = User::factory()->create([
        'email' => 'tenant-owner@example.test',
        'has_explicit_role' => true,
    ]);
    $owner->assignRole('Usuario');

    $subordinate = User::factory()->create([
        'email' => 'tenant-subordinate@example.test',
        'owner_id' => $owner->id,
        'creator_id' => $owner->id,
        'has_explicit_role' => true,
    ]);
    $subordinate->assignRole('Usuario');

    $client = Cliente::factory()->create(['owner_id' => $owner->id]);

    $sale = Venta::factory()->create([
        'cliente_id' => $client->id,
        'user_id' => $subordinate->id,
        'owner_id' => $owner->id,
    ]);

    $customRole = Role::create([
        'name' => 'Rol del negocio',
        'guard_name' => 'web',
        'owner_id' => $owner->id,
        'level' => 3,
    ]);

    $subordinate->createToken('tenant-owner-delete-token');

    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $subordinate->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $otherOwner = User::factory()->create([
        'email' => 'unrelated-tenant-owner@example.test',
        'has_explicit_role' => true,
    ]);
    $otherUser = managedUserForDelete($otherOwner, [
        'email' => 'unrelated-tenant-user@example.test',
    ]);
    $otherClient = Cliente::factory()->create(['owner_id' => $otherOwner->id]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $owner), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $owner->id]);
    $this->assertDatabaseMissing('users', ['id' => $subordinate->id]);
    $this->assertDatabaseMissing('clientes', ['id' => $client->id]);
    $this->assertDatabaseMissing('ventas', ['id' => $sale->id]);
    $this->assertDatabaseMissing('roles', ['id' => $customRole->id]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $subordinate->id]);
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $subordinate->id,
        'tokenable_type' => $subordinate->getMorphClass(),
    ]);

    $this->assertDatabaseHas('users', ['id' => $otherOwner->id]);
    $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
    $this->assertDatabaseHas('clientes', ['id' => $otherClient->id]);
});

test('a user with the owner role is deleted with their company data', function () {
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

    $subordinate = User::factory()->create([
        'email' => 'owner-role-subordinate@example.test',
        'owner_id' => $owner->id,
        'creator_id' => $owner->id,
        'has_explicit_role' => true,
    ]);
    $subordinate->assignRole('Usuario');

    $client = Cliente::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $owner), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $owner->id]);
    $this->assertDatabaseMissing('users', ['id' => $subordinate->id]);
    $this->assertDatabaseMissing('clientes', ['id' => $client->id]);
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

test('a super admin cannot delete users even with all permissions', function () {
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

test('a user with control total permission cannot delete users', function () {
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
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('master can delete an administrator', function () {
    $adminRole = Role::firstOrCreate(
        ['name' => 'Administrador', 'guard_name' => 'web'],
    );
    $adminRole->update(['level' => 2]);

    $admin = managedUserForDelete($this->master, ['email' => 'delete-admin-by-master@example.test']);
    $admin->assignRole('Administrador');

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $admin), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $admin->id]);
});

test('master can delete a super admin', function () {
    $superAdminRole = Role::firstOrCreate(
        ['name' => 'Super Admin', 'guard_name' => 'web'],
        ['owner_id' => null],
    );
    $superAdminRole->update(['level' => 1]);

    $superAdmin = managedUserForDelete($this->master, ['email' => 'delete-super-admin-by-master@example.test']);
    $superAdmin->assignRole('Super Admin');

    $this->actingAs($this->master)
        ->from(route('usuarios-roles.index'))
        ->delete(route('usuarios-roles.user.destroy', $superAdmin), deleteUserPayload())
        ->assertRedirect(route('usuarios-roles.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $superAdmin->id]);
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
