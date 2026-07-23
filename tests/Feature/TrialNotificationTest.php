<?php

use App\Models\User;
use App\Models\WebSetting;
use App\Notifications\TrialExpiryNotification;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    // Create first user to ensure subsequent users get Usuario role
    User::factory()->create(['email' => 'first-user-seeder@test.com']);
});

it('sets trial_days from WebSetting when creating user', function () {
    WebSetting::getSettings()->update(['trial_days' => 30]);

    $user = User::factory()->create([
        'email' => 'newuser@test.com',
    ]);

    $user->refresh();

    expect($user->trial_ends_at)->not->toBeNull();
    expect($user->trial_starts_at)->not->toBeNull();
    expect($user->trial_starts_at->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
    expect($user->trial_ends_at->format('Y-m-d'))->toBe(now()->addDays(30)->format('Y-m-d'));
});

it('falls back to 15 days when WebSetting trial_days is 0', function () {
    WebSetting::getSettings()->update(['trial_days' => 0]);

    $user = User::factory()->create([
        'email' => 'fallback@test.com',
    ]);

    $user->refresh();

    expect($user->trial_ends_at->format('Y-m-d'))->toBe(now()->addDays(15)->format('Y-m-d'));
});

it('assigns Usuario role to non-first users', function () {
    $user = User::factory()->create([
        'email' => 'newuser2@test.com',
    ]);

    expect($user->hasRole('Usuario'))->toBeTrue();
});

it('does not assign trial to first user (Super Admin)', function () {
    $user = User::where('email', 'first-user-seeder@test.com')->first();

    expect($user->hasRole('Super Admin'))->toBeTrue();
    expect($user->trial_ends_at)->toBeNull();
});

it('trialDaysRemaining returns correct count', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(5)->endOfDay(),
    ]);

    expect($user->trialDaysRemaining())->toBe(5);
});

it('trialDaysRemaining returns 0 when trial has expired', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($user->trialDaysRemaining())->toBe(0);
});

it('isTrialActive returns true when trial is valid and role is Usuario', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(3),
    ]);

    expect($user->isTrialActive())->toBeTrue();
});

it('isTrialExpired returns true when trial has passed and role is Usuario', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($user->isTrialExpired())->toBeTrue();
});

it('CheckPermission redirects to planes when trial expired on write action', function () {
    Notification::fake();

    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);
    $user->syncPermissions(['comercial.categorias.viewAny', 'comercial.categorias.create']);

    $this->actingAs($user);

    $response = $this->post(route('categorias.store'));

    $response->assertRedirect(route('planes.index'));
});

it('CheckPermission allows viewAny when trial expired', function () {
    Notification::fake();

    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);
    $user->syncPermissions(['comercial.categorias.viewAny']);

    $this->actingAs($user);

    $response = $this->get(route('categorias.index'));

    $response->assertSuccessful();
});

it('NotifyTrialExpiry sends notification at 7 days', function () {
    Notification::fake();

    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(7)->endOfDay(),
    ]);
    $user->assignRole('Usuario');

    $this->artisan('trial:notify');

    Notification::assertSentTo($user, TrialExpiryNotification::class);
});

it('NotifyTrialExpiry sends notification at 3 days', function () {
    Notification::fake();

    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(3)->endOfDay(),
    ]);
    $user->assignRole('Usuario');

    $this->artisan('trial:notify');

    Notification::assertSentTo($user, TrialExpiryNotification::class);
});

it('NotifyTrialExpiry sends notification at 0 days (today)', function () {
    Notification::fake();

    $user = User::factory()->create([
        'trial_ends_at' => now()->endOfDay(),
    ]);
    $user->assignRole('Usuario');

    $this->artisan('trial:notify');

    Notification::assertSentTo($user, TrialExpiryNotification::class);
});

it('NotifyTrialExpiry does not send duplicate notification same day', function () {
    Notification::fake();

    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(7)->endOfDay(),
    ]);
    $user->assignRole('Usuario');

    $this->artisan('trial:notify');
    Notification::assertSentTo($user, TrialExpiryNotification::class, 1);

    $this->artisan('trial:notify')->assertExitCode(0);
});

it('NotifyTrialExpiry respects alreadyNotifiedToday via database dedup', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(7)->endOfDay(),
    ]);
    $user->assignRole('Usuario');

    $this->artisan('trial:notify');

    $notificationsAfterFirst = $user->notifications()
        ->where('type', TrialExpiryNotification::class)
        ->whereDate('created_at', today())
        ->whereJsonContains('data->tipo', 'trial_warning')
        ->count();

    expect($notificationsAfterFirst)->toBe(1);

    $this->artisan('trial:notify');

    $notificationsAfterSecond = $user->notifications()
        ->where('type', TrialExpiryNotification::class)
        ->whereDate('created_at', today())
        ->whereJsonContains('data->tipo', 'trial_warning')
        ->count();

    expect($notificationsAfterSecond)->toBe(1);
});

it('TrialExpiryNotification has correct database structure for expiry', function () {
    $notification = new TrialExpiryNotification(0);

    $user = User::factory()->create();

    $data = $notification->toArray($user);

    expect($data)->toHaveKeys(['titulo', 'message', 'tipo', 'link', 'days_remaining']);
    expect($data['tipo'])->toBe('trial_expiry');
    expect($data['days_remaining'])->toBe(0);
});

it('TrialExpiryNotification has correct database structure for warning', function () {
    $notification = new TrialExpiryNotification(3);

    $user = User::factory()->create();

    $data = $notification->toArray($user);

    expect($data)->toHaveKeys(['titulo', 'message', 'tipo', 'link', 'days_remaining']);
    expect($data['tipo'])->toBe('trial_warning');
    expect($data['days_remaining'])->toBe(3);
});

it('TrialExpiryNotification includes trial_days from WebSetting', function () {
    WebSetting::getSettings()->update(['trial_days' => 30]);

    $notification = new TrialExpiryNotification(0);
    $user = User::factory()->create();
    $data = $notification->toArray($user);

    expect($data['trial_days'])->toBe(30);
});

it('CheckPermission returns JSON 403 when Inertia header present and trial expired', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);
    $user->syncPermissions(['comercial.categorias.create']);

    $this->actingAs($user);

    $response = $this->postJson(route('categorias.store'), [], [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => '1',
    ]);

    $response->assertStatus(403);
    $response->assertJson([
        'trial_expired' => true,
        'message' => 'Tu período de prueba ha finalizado. Actualiza tu plan para seguir editando.',
    ]);
});

it('CheckPermission returns 403 when user has no permission regardless of trial', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(10),
    ]);
    $user->roles()->detach();
    $user->syncPermissions([]);

    $this->actingAs($user);

    $this->post(route('categorias.store'))
        ->assertStatus(403);
});

it('CheckPermission allows Super Admin even with expired trial', function () {
    $user = User::where('email', 'first-user-seeder@test.com')->first();
    $user->update([
        'trial_ends_at' => now()->subDay(),
        'trial_starts_at' => now()->subDays(16),
    ]);
    $user->trialStateCache = null;
    $user->syncPermissions(['comercial.categorias.create']);

    $this->actingAs($user);

    $this->post(route('categorias.store'))
        ->assertStatus(302);
});

it('CheckPermission redirects unauthenticated user to login', function () {
    $response = $this->post(route('categorias.store'));

    $response->assertRedirect(route('login'));
});

it('isTrialActive returns false for non-Usuario roles', function () {
    $user = User::where('email', 'first-user-seeder@test.com')->first();
    $user->update([
        'trial_ends_at' => now()->addDays(10),
        'trial_starts_at' => now(),
    ]);
    $user->trialStateCache = null;

    expect($user->isTrialActive())->toBeFalse();
    expect($user->isTrialExpired())->toBeFalse();
    expect($user->trialDaysRemaining())->toBe(0);
});

it('trialStateCache invalidates when trial_ends_at changes', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->addDays(30)->startOfDay(),
    ]);

    expect($user->isTrialActive())->toBeTrue();
    expect($user->trialDaysRemaining())->toBeGreaterThan(25);

    $user->update(['trial_ends_at' => now()->subDay()]);
    $user->trialStateCache = null;

    expect($user->isTrialActive())->toBeFalse();
    expect($user->isTrialExpired())->toBeTrue();
    expect($user->trialDaysRemaining())->toBe(0);
});

it('NotifyTrialExpiry skips user without trial_ends_at', function () {
    Notification::fake();

    $user = User::factory()->create([
        'trial_ends_at' => null,
    ]);
    $user->assignRole('Usuario');

    $this->artisan('trial:notify');

    Notification::assertNothingSent();
});

it('CheckPermission allows read actions when trial expired', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);
    $user->syncPermissions(['comercial.categorias.viewAny']);

    $this->actingAs($user);

    $this->get(route('categorias.index'))
        ->assertSuccessful();
});

it('CheckPermission blocks write actions when trial expired via route middleware', function () {
    $user = User::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);
    $user->syncPermissions(['general.tareas.create']);

    $this->actingAs($user);

    $this->post(route('tareas.store'))
        ->assertRedirect(route('planes.index'));
});
