<?php

use App\Models\User;

it('restores the session from the remember cookie', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $response->assertRedirect();

    $recallerName = auth()->guard()->getRecallerName();
    $recaller = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === $recallerName);

    expect($recaller)->not->toBeNull();

    dump([
        'name' => $recaller->getName(),
        'expires' => $recaller->getExpiresTime(),
        'expires_in_days' => round(($recaller->getExpiresTime() - time()) / 86400, 1),
        'secure' => $recaller->isSecure(),
        'httpOnly' => $recaller->isHttpOnly(),
        'sameSite' => $recaller->getSameSite(),
        'path' => $recaller->getPath(),
        'domain' => $recaller->getDomain(),
    ]);

    // Simula un navegador nuevo: sin sesión, solo la cookie "remember me".
    $this->flushSession();
    $this->app['auth']->forgetGuards();

    $response = $this->withUnencryptedCookie($recallerName, $recaller->getValue())
        ->get(route('dashboard'));

    $response->assertOk();
    $this->assertAuthenticatedAs($user);
});
