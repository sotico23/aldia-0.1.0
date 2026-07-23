<?php

use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('redirects provider users to proveedor.dashboard', function () {
    $role = Role::firstOrCreate(['name' => 'Proveedor']);
    $this->user->assignRole('Proveedor');

    Proveedor::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->user->id,
    ]);

    $this->user->refresh();
    expect($this->user->isProveedor())->toBeTrue();

    Auth::login($this->user);

    $request = Request::create('/login', 'POST', [], [], [], [
        'HTTP_X-Inertia' => 'true',
        'HTTP_Accept' => 'text/html, application/xhtml+xml',
    ]);

    $loginResponse = app(LoginResponse::class);
    $response = $loginResponse->toResponse($request);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toEndWith('/proveedor');
});

it('redirects client users to cliente.dashboard', function () {
    $role = Role::firstOrCreate(['name' => 'Cliente']);
    $this->user->assignRole('Cliente');

    Cliente::factory()->create([
        'user_id' => $this->user->id,
        'owner_id' => $this->user->id,
    ]);

    $this->user->refresh();
    expect($this->user->isCliente())->toBeTrue();

    Auth::login($this->user);

    $request = Request::create('/login', 'POST', [], [], [], [
        'HTTP_X-Inertia' => 'true',
        'HTTP_Accept' => 'text/html, application/xhtml+xml',
    ]);

    $loginResponse = app(LoginResponse::class);
    $response = $loginResponse->toResponse($request);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toEndWith('/cliente');
});

it('falls back to dashboard for regular users', function () {
    expect($this->user->isProveedor())->toBeFalse();
    expect($this->user->isCliente())->toBeFalse();

    Auth::login($this->user);

    $request = Request::create('/login', 'POST', [], [], [], [
        'HTTP_X-Inertia' => 'true',
        'HTTP_Accept' => 'text/html, application/xhtml+xml',
    ]);

    $loginResponse = app(LoginResponse::class);
    $response = $loginResponse->toResponse($request);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toEndWith('/dashboard');
});
