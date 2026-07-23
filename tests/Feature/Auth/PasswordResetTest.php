<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('password reset requests are rate limited', function () {
    // ThrottleRequests middleware hashes the key: md5('password-reset' . $key)
    // where $key = Str::lower($email . '|' . $ip)
    $email = 'test@example.com';
    $ip = '127.0.0.1';
    $key = strtolower($email.'|'.$ip);
    $hashedKey = md5('password-reset'.$key);

    RateLimiter::increment($hashedKey, amount: 3);

    $response = $this->post(route('password.email'), ['email' => $email]);

    $response->assertTooManyRequests();
});

test('registration requests are rate limited', function () {
    // ThrottleRequests middleware hashes the key: md5('registration' . $ip)
    // where $key = $request->ip() (default test IP is 127.0.0.1)
    $ip = '127.0.0.1';
    $hashedKey = md5('registration'.$ip);

    RateLimiter::increment($hashedKey, amount: 5);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertTooManyRequests();
});
