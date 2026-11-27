<?php

use App\Livewire\Empleados\Index;
use App\Models\Empleado;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

test('el modal ver muestra los datos del empleado con el nuevo diseño', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo(Permission::findOrCreate('rrhh.empleados.viewAny'));
    $this->actingAs($owner);

    $empleado = Empleado::factory()->create([
        'owner_id' => $owner->getOwnerId(),
        'nombre' => 'Ana',
        'apellido' => 'Prueba',
        'email' => 'ana@ejemplo.test',
        'rut' => '12.345.678-9',
    ]);

    Livewire::test(Index::class)
        ->call('ver', $empleado->id)
        ->assertSet('detalle', $empleado->id)
        ->assertSee('Detalle de empleado')
        ->assertSee('Ana Prueba')
        ->assertSee('Datos personales')
        ->assertSee('Contrato y jornada')
        ->assertSee('Previsión y datos bancarios')
        ->assertSee('Acceso a la plataforma');
});

test('los modales nuevo y editar usan la cabecera con color', function () {
    $owner = User::factory()->create();
    foreach (['rrhh.empleados.viewAny', 'rrhh.empleados.create', 'rrhh.empleados.edit'] as $permiso) {
        $owner->givePermissionTo(Permission::findOrCreate($permiso));
    }
    $this->actingAs($owner);

    $empleado = Empleado::factory()->create([
        'owner_id' => $owner->getOwnerId(),
        'nombre' => 'Pedro',
        'apellido' => 'Ejemplo',
    ]);

    Livewire::test(Index::class)
        ->call('nuevo')
        ->assertSee('Registro de empleado')
        ->assertSee('Datos personales')
        ->call('cerrar')
        ->call('editar', $empleado->id)
        ->assertSee('Edición de empleado')
        ->assertSee('Pedro Ejemplo');
});
