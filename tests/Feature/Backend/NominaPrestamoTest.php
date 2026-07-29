<?php

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('calcularProporcional includes loan deduction fields', function () {
    $empleado = Empleado::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'sueldo_liquido_pactado' => 600000,
        'estado' => 'activo',
    ]);

    $this->actingAs($this->user);

    $response = $this->getJson(route('nominas.calcular').'?periodo=2026-07');
    $response->assertSuccessful();

    $calculos = $response->json();
    $empleadoCalc = collect($calculos)->firstWhere('empleado_id', $empleado->id);

    expect($empleadoCalc)->not->toBeNull();
    expect(array_key_exists('total_descuento_prestamos', $empleadoCalc))->toBeTrue();
    expect(array_key_exists('sueldo_liquido', $empleadoCalc))->toBeTrue();
    expect(array_key_exists('cuotas_prestamo_ids', $empleadoCalc))->toBeTrue();
});

test('calculo sin prestamos liquido equals sueldo proporcional', function () {
    $empleado = Empleado::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'sueldo_liquido_pactado' => 900000,
        'estado' => 'activo',
    ]);

    $this->actingAs($this->user);

    $response = $this->getJson(route('nominas.calcular').'?periodo=2026-07');
    $response->assertSuccessful();

    $calculos = $response->json();
    $empleadoCalc = collect($calculos)->firstWhere('empleado_id', $empleado->id);

    expect($empleadoCalc['total_descuento_prestamos'])->toBe(0);
    expect($empleadoCalc['sueldo_liquido'])->toBe($empleadoCalc['sueldo_proporcional']);
});
