<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'test-app');
});

test('el navegador autoriza canales privados usando su sesion', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->withSession([Auth::guard('web')->getName() => $user->id])
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-user.'.$user->id,
        ])->assertSuccessful()->assertJsonStructure(['auth']);
});

test('la apk autoriza canales privados usando bearer token', function (string $endpoint) {
    $user = User::factory()->create(['is_active' => true]);

    $this->withToken($user->createToken('apk')->plainTextToken)
        ->postJson($endpoint, [
            'socket_id' => '123.456',
            'channel_name' => 'private-user.'.$user->id,
        ])->assertSuccessful()->assertJsonStructure(['auth']);
})->with(['/broadcasting/auth', '/api/broadcasting/auth']);

test('un token no permite acceder al canal privado de otro usuario', function () {
    $user = User::factory()->create(['is_active' => true]);
    $otherUser = User::factory()->create();

    $this->withToken($user->createToken('apk')->plainTextToken)
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-user.'.$otherUser->id,
        ])->assertForbidden();
});

test('la autorizacion de canales rechaza clientes sin autenticar', function (string $endpoint) {
    $this->postJson($endpoint, [
        'socket_id' => '123.456',
        'channel_name' => 'private-user.1',
    ])->assertUnauthorized();
})->with(['/broadcasting/auth', '/api/broadcasting/auth']);
