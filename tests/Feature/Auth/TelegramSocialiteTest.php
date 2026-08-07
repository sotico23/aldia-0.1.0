<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

function telegramSocialiteUser(string $id): Laravel\Socialite\Contracts\User
{
    $socialiteUser = Mockery::mock(Laravel\Socialite\Contracts\User::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn(null);
    $socialiteUser->shouldReceive('getName')->andReturn('Telegram User');
    $socialiteUser->shouldReceive('getNickName')->andReturn('telegramuser');
    $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/telegram.png');

    return $socialiteUser;
}

test('telegram redirect renders the telegram login widget', function () {
    $response = $this->get('/auth/telegram/redirect');

    $response->assertStatus(200);
    $response->assertSee('telegram-widget.js');
});

test('telegram callback without email asks for email and does not create the user', function () {
    $socialiteUser = telegramSocialiteUser('123456789');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->get('/auth/telegram/callback')
        ->assertRedirect(route('socialite.telegram.email-form'));

    expect(User::count())->toBe(0);
    expect(session('telegram_pending.id'))->toBe('123456789');
});

test('telegram callback completes registration with a valid email', function () {
    $socialiteUser = telegramSocialiteUser('123456789');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->get('/auth/telegram/callback');

    $response = $this->post('/auth/telegram/email', ['email' => 'telegram@example.com']);

    $response->assertRedirect(route('dashboard'));

    $user = User::where('telegram_id', '123456789')->first();
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('telegram@example.com');
    expect($user->telegram_username)->toBe('telegramuser');
    expect($user->profile_photo_path)->toBe('https://example.com/telegram.png');
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('', $user->password))->toBeFalse();
});

test('telegram callback links the telegram id to an existing account with the same email', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    $socialiteUser = telegramSocialiteUser('987654321');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->get('/auth/telegram/callback');

    $this->post('/auth/telegram/email', ['email' => 'existing@example.com'])
        ->assertRedirect(route('dashboard'));

    expect(User::count())->toBe(1);
    expect($user->fresh()->telegram_id)->toBe('987654321');
    expect($user->fresh()->telegram_username)->toBe('telegramuser');
});

test('telegram callback logs in an existing telegram user directly', function () {
    $user = User::factory()->create([
        'telegram_id' => '555555555',
        'telegram_username' => 'pepe',
        'email_verified_at' => now(),
    ]);

    $socialiteUser = telegramSocialiteUser('555555555');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->get('/auth/telegram/callback')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('telegram email form requires a pending telegram session', function () {
    $this->get(route('socialite.telegram.email-form'))
        ->assertRedirect(route('login'));
});

test('telegram email store requires a pending telegram session', function () {
    $this->post('/auth/telegram/email', ['email' => 'nopending@example.com'])
        ->assertRedirect(route('login'));

    expect(User::count())->toBe(0);
});
