<?php

use App\Models\Subscription;
use App\Models\User;
use App\Models\WebSetting;
use Inertia\Testing\AssertableInertia as Assert;

function configurarPlanesDePrueba(bool $activas): void
{
    WebSetting::create([
        'user_id' => null,
        'app_name' => 'Test',
        'subscriptions_active' => $activas,
        'default_currency' => 'CLP',
        'trial_days' => 5,
        'auto_renewal' => true,
        'planes' => [
            [
                'nombre' => 'Gratuito',
                'precio' => '$0',
                'periodo' => '/mes',
                'descripcion' => 'Plan gratuito',
                'popular' => false,
                'caracteristicas' => [],
            ],
            [
                'nombre' => 'Vendedor Independiente',
                'precio' => '$29',
                'periodo' => '/mes',
                'descripcion' => 'Para vendedores',
                'popular' => false,
                'caracteristicas' => ['Hasta 100 clientes'],
            ],
            [
                'nombre' => 'Corporativo',
                'precio' => 'Custom',
                'periodo' => '',
                'descripcion' => 'Contactar ventas',
                'popular' => false,
                'caracteristicas' => [],
            ],
        ],
    ]);
    WebSetting::clearCache();
}

beforeEach(function () {
    WebSetting::clearCache();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

afterEach(function () {
    WebSetting::clearCache();
});

test('la página de planes usa el tag planes y la configuración global', function () {
    configurarPlanesDePrueba(true);

    $this->get(route('planes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Planes/Index')
            ->has('planes', 3)
            ->where('config.subscriptions_active', true)
            ->where('config.default_currency', 'CLP')
            ->where('gateway', 'manual'));
});

test('registra la suscripción al plan elegido', function () {
    configurarPlanesDePrueba(true);

    $this->from(route('planes.index'))
        ->post(route('planes.subscribe'), ['plan' => 1])
        ->assertRedirect(route('planes.index'));

    $this->assertDatabaseHas('subscriptions', [
        'business_id' => $this->user->id,
        'status' => 'trial',
        'price' => 29,
        'gateway' => 'manual',
    ]);
    expect(Subscription::first()->metadata['nombre_plan'])
        ->toBe('Vendedor Independiente');
});

test('no permite suscribirse si las suscripciones están desactivadas', function () {
    configurarPlanesDePrueba(false);

    $this->from(route('planes.index'))
        ->post(route('planes.subscribe'), ['plan' => 1])
        ->assertSessionHasErrors('plan');

    expect(Subscription::count())->toBe(0);
});

test('rechaza un índice de plan inválido', function () {
    configurarPlanesDePrueba(true);

    $this->from(route('planes.index'))
        ->post(route('planes.subscribe'), ['plan' => 99])
        ->assertSessionHasErrors('plan');
});
