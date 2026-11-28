<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

test('Shop social login binds the browser callback to a single exchange in the app', function () {
    $user = User::factory()->create();
    $verifier = str_repeat('a', 43);
    $challenge = hash('sha256', $verifier);
    $provider = Mockery::mock(SocialiteUser::class);
    $provider->shouldReceive('getEmail')->andReturn($user->email);
    $provider->shouldReceive('getName')->andReturn($user->name);
    $provider->shouldReceive('getNickName')->andReturn(null);
    $provider->shouldReceive('getAvatar')->andReturn(null);
    Socialite::shouldReceive('driver->redirect')->once()->andReturn(redirect()->away('https://example.com/oauth'));
    Socialite::shouldReceive('driver->user')->once()->andReturn($provider);

    $this->get('/shop/auth/google?challenge='.$challenge)->assertRedirect('https://example.com/oauth');
    $response = $this->get('/auth/google/callback')->assertRedirect();
    $callback = parse_url($response->headers->get('Location'));
    expect($callback['scheme'])->toBe('cl.redcliente.shop');
    expect($callback['host'])->toBe('auth');
    parse_str($callback['query'], $query);
    expect($query['state'])->toBe($challenge);

    $endpoint = '/api/v1/auth/shop/exchange';
    $credentials = ['code' => $query['code'], 'verifier' => $verifier];
    $this->postJson($endpoint, array_replace($credentials, ['verifier' => str_repeat('b', 43)]))
        ->assertUnauthorized();
    $this->postJson($endpoint, $credentials)->assertOk()->assertJsonPath('data.user.id', $user->id);
    $this->postJson($endpoint, $credentials)->assertUnauthorized();
});
