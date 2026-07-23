<?php

use App\Models\User;
use App\Models\WebSetting;
use Inertia\Testing\AssertableInertia as Assert;

test('flash messages are shared with the frontend on the home page', function () {
    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('flash')
            ->has('flash.success')
            ->has('flash.error')
        );
});

test('flash messages are shared with the frontend when authenticated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->has('flash')
            ->has('flash.success')
            ->has('flash.error')
        );
});

test('success flash message is correctly propagated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['success' => 'Operación completada con éxito'])
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', 'Operación completada con éxito')
        );
});

test('error flash message is correctly propagated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['error' => 'Hubo un problema al procesar la solicitud'])
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.error', 'Hubo un problema al procesar la solicitud')
        );
});

test('validation errors are available in the shared props', function () {
    $user = User::factory()->create();
    $setting = WebSetting::first() ?: WebSetting::factory()->create();

    // Simulating a failed validation redirect
    $this->actingAs($user)
        ->from('/configuracion-web')
        ->put("/configuracion-web/{$setting->id}", []) // Missing required fields triggers validation
        ->assertRedirect('/configuracion-web');

    // After redirect, errors should be in the session and shared via Inertia
    $this->actingAs($user)
        ->get('/configuracion-web')
        ->assertInertia(fn (Assert $page) => $page
            ->has('errors')
        );
});
