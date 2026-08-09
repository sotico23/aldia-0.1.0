<?php

use App\Models\User;
use App\Models\WebSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

const TELEGRAM_BOT_TOKEN = '123456789:AAAaAaAaAaAaAaAaAaAaAaA';

function telegramSignedQuery(array $data, string $token = TELEGRAM_BOT_TOKEN): array
{
    $dataCheckString = collect($data)
        ->except('hash')
        ->map(fn (mixed $value, string $key): string => "{$key}={$value}")
        ->sort()
        ->values()
        ->join("\n");

    $secretKey = hash('sha256', $token, true);

    return $data + ['hash' => hash_hmac('sha256', $dataCheckString, $secretKey)];
}

beforeEach(function () {
    config(['services.telegram.client_secret' => TELEGRAM_BOT_TOKEN]);
});

test('telegram redirect renders the login widget when no hash is present', function () {
    $this->get('/auth/telegram/redirect')
        ->assertSuccessful()
        ->assertSee('telegram-widget.js');
});

test('telegram redirect processes the widget payload and logs in an existing user', function () {
    $user = User::factory()->create([
        'telegram_id' => '5301396120',
        'telegram_username' => 'RedCliente',
        'email_verified_at' => now(),
    ]);

    $query = telegramSignedQuery([
        'auth_date' => time(),
        'first_name' => 'Ezequiel',
        'id' => '5301396120',
        'last_name' => 'Soto',
        'photo_url' => 'https://example.com/photo.png',
        'username' => 'RedCliente',
    ]);

    $this->get('/auth/telegram/redirect?'.http_build_query($query))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('telegram callback rejects a callback with an invalid hash', function () {
    $query = telegramSignedQuery([
        'auth_date' => time(),
        'first_name' => 'Ezequiel',
        'id' => '5301396120',
    ]);

    $query['id'] = '9999999999';

    $this->get('/auth/telegram/callback?'.http_build_query($query))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('telegram callback rejects an expired auth_date to prevent replay attacks', function () {
    $query = telegramSignedQuery([
        'auth_date' => time() - 600,
        'first_name' => 'Ezequiel',
        'id' => '5301396120',
    ]);

    $this->get('/auth/telegram/callback?'.http_build_query($query))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('telegram callback without hash is rejected gracefully', function () {
    $this->get('/auth/telegram/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
});

test('telegram callback logs in an existing telegram user directly', function () {
    $user = User::factory()->create([
        'telegram_id' => '555555555',
        'telegram_username' => 'telegramuser',
        'email_verified_at' => now(),
    ]);

    $query = telegramSignedQuery([
        'auth_date' => time(),
        'first_name' => 'Telegram',
        'id' => '555555555',
        'last_name' => 'User',
        'username' => 'telegramuser',
    ]);

    $this->get('/auth/telegram/callback?'.http_build_query($query))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('telegram widget posts the signed payload to the callback and logs in the user', function () {
    $user = User::factory()->create([
        'telegram_id' => '777777777',
        'telegram_username' => 'widgetuser',
        'email_verified_at' => now(),
    ]);

    $query = telegramSignedQuery([
        'auth_date' => time(),
        'first_name' => 'Widget',
        'id' => '777777777',
        'username' => 'widgetuser',
    ]);

    $this->post('/auth/telegram/callback', $query)
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('telegram callback creates a user from a POST payload with email', function () {
    $payload = telegramSignedQuery([
        'auth_date' => time(),
        'email' => 'post@example.com',
        'first_name' => 'Post',
        'id' => '424242424',
        'username' => 'postuser',
    ]);

    $this->post('/auth/telegram/callback', $payload)
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $user = User::where('email', 'post@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->telegram_id)->toBe('424242424');
    expect($user->telegram_username)->toBe('postuser');
});

test('login page exposes the telegram bot username from web settings', function () {
    WebSetting::factory()->create([
        'telegram_login_bot_name' => '@redcliente_login_bot',
        'telegram_login_bot_token' => '123456789:AAExampleToken',
        'telegram_login_redirect_uri' => 'https://example.test/auth/telegram/callback',
    ]);

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('telegramBotUsername', 'redcliente_login_bot'),
        );
});

test('telegram callback creates a new user when telegram provides an email', function () {
    $query = telegramSignedQuery([
        'auth_date' => time(),
        'email' => 'nuevo@example.com',
        'first_name' => 'Telegram',
        'id' => '123456789',
        'last_name' => 'User',
        'username' => 'telegramuser',
    ]);

    $this->get('/auth/telegram/callback?'.http_build_query($query))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $user = User::where('email', 'nuevo@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->telegram_id)->toBe('123456789');
    expect($user->telegram_username)->toBe('telegramuser');
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('', $user->password))->toBeFalse();
});

test('telegram callback links the telegram id to an existing account with the same email', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    $query = telegramSignedQuery([
        'auth_date' => time(),
        'email' => 'existing@example.com',
        'first_name' => 'Telegram',
        'id' => '987654321',
        'username' => 'telegramuser',
    ]);

    $this->get('/auth/telegram/callback?'.http_build_query($query))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    expect(User::count())->toBe(1);
    expect($user->fresh()->telegram_id)->toBe('987654321');
    expect($user->fresh()->telegram_username)->toBe('telegramuser');
});

test('telegram callback without an email stores the pending data and asks for an email', function () {
    $query = telegramSignedQuery([
        'auth_date' => time(),
        'first_name' => 'Telegram',
        'id' => '123456789',
        'last_name' => 'User',
        'photo_url' => 'https://example.com/telegram.png',
        'username' => 'telegramuser',
    ]);

    $this->get('/auth/telegram/callback?'.http_build_query($query))
        ->assertRedirect(route('telegram.email-form'));

    expect(User::count())->toBe(0);
    expect(session('telegram_pending.id'))->toBe('123456789');
    expect(session('telegram_pending.username'))->toBe('telegramuser');
    expect(session('telegram_pending.name'))->toBe('Telegram User');
});

test('telegram callback completes registration with a valid email', function () {
    $query = telegramSignedQuery([
        'auth_date' => time(),
        'first_name' => 'Telegram',
        'id' => '123456789',
        'username' => 'telegramuser',
    ]);

    $this->get('/auth/telegram/callback?'.http_build_query($query));

    $this->post('/auth/telegram/email', ['email' => 'telegram@example.com'])
        ->assertRedirect(route('dashboard'));

    $user = User::where('telegram_id', '123456789')->first();

    expect($user)->not->toBeNull();
    expect($user->email)->toBe('telegram@example.com');
    expect($user->telegram_username)->toBe('telegramuser');
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('', $user->password))->toBeFalse();
});

test('telegram email completion links the telegram id to an existing account', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    $query = telegramSignedQuery([
        'auth_date' => time(),
        'first_name' => 'Telegram',
        'id' => '987654321',
        'username' => 'telegramuser',
    ]);

    $this->get('/auth/telegram/callback?'.http_build_query($query));

    $this->post('/auth/telegram/email', ['email' => 'existing@example.com'])
        ->assertRedirect(route('dashboard'));

    expect(User::count())->toBe(1);
    expect($user->fresh()->telegram_id)->toBe('987654321');
    expect($user->fresh()->telegram_username)->toBe('telegramuser');
});

test('telegram email form requires a pending telegram session', function () {
    $this->get(route('telegram.email-form'))
        ->assertRedirect(route('login'));
});

test('telegram email store requires a pending telegram session', function () {
    $this->post('/auth/telegram/email', ['email' => 'nopending@example.com'])
        ->assertRedirect(route('login'));

    expect(User::count())->toBe(0);
});
