<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password is hashed in database', function () {
    $user = User::factory()->create(['password' => 'secret']);

    expect(Hash::needsRehash($user->password))->toBeFalse();
});

test('users cannot login with incorrect password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'incorrect-password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('users cannot login with non-existent email', function () {
    $response = $this->post(route('login.store'), [
        'email' => 'nonexistent@example.com',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('logout invalidates session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
});

test('unauthenticated users cannot access protected routes', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can access protected routes', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('remember me cookie is set when requested', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $response->assertCookie(auth()->guard()->getRecallerName());
});

test('password reset request returns success for valid email', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $response->assertSessionHas('status');
});
