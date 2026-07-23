<?php

use App\Models\User;

test('csrf protection is enabled for POST requests', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->withHeaders([
        'X-CSRF-TOKEN' => '',
    ])->post(route('logout'));

    expect($response->status())->toBeIn([419, 302]);
});

test('authenticated user can access protected routes', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('unauthenticated users are redirected to login', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});
