<?php

use App\Models\User;
use App\Notifications\TempPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

test('Socialite callback does not send temp password notification', function () {
    Notification::fake();

    $socialiteUser = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $socialiteUser->shouldReceive('getEmail')->andReturn('social@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('Social User');
    $socialiteUser->shouldReceive('getNickName')->andReturn('socialuser');
    $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.png');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('dashboard'));

    $user = User::where('email', 'social@example.com')->first();
    expect($user)->not->toBeNull();

    Notification::assertNotSentTo($user, TempPasswordNotification::class);
});

test('Socialite existing user without password gets random hash not sent via email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'password' => Hash::make(Str::random(32)),
    ]);

    $socialiteUser = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $socialiteUser->shouldReceive('getEmail')->andReturn('existing@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('Existing User');
    $socialiteUser->shouldReceive('getNickName')->andReturn('existinguser');
    $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.png');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->password)->not->toBeNull();
    expect(Hash::check(Str::random(32), $user->password))->toBeFalse();

    Notification::assertNotSentTo($user, TempPasswordNotification::class);
});

test('Socialite new user created with random password hash, no notification', function () {
    Notification::fake();

    $socialiteUser = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $socialiteUser->shouldReceive('getEmail')->andReturn('newuser@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('New User');
    $socialiteUser->shouldReceive('getNickName')->andReturn('newuser');
    $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.png');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('dashboard'));

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->password)->not->toBeNull();

    Notification::assertNotSentTo($user, TempPasswordNotification::class);
});
