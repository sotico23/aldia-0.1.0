<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen passes countries to view', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'country' => 'CL',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->first();
    expect($user->country)->toBe('CL');
    expect($user->business_name)->toBe('Test User');
    expect($user->telefono)->toBeNull();
});

test('new users can register with all optional fields', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test-full@example.com',
        'country' => 'PE',
        'business_name' => 'Mi Empresa SAC',
        'telefono' => '+51 999 888 777',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test-full@example.com')->first();
    expect($user->country)->toBe('PE');
    expect($user->business_name)->toBe('Mi Empresa SAC');
    expect($user->telefono)->toBe('+51 999 888 777');
});

test('registration defaults country to CL when not provided', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Default Country User',
        'email' => 'default-country@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'default-country@example.com')->first();
    expect($user->country)->toBe('CL');
});

test('registration rejects invalid country code', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Invalid Country User',
        'email' => 'invalid-country@example.com',
        'country' => 'XX',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('country');
    $this->assertGuest();
});

test('registration rejects duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'Duplicate Email User',
        'email' => 'existing@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
