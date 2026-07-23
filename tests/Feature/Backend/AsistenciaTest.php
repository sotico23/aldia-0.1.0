<?php

use App\Models\User;

test('asistencia store validation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/asistencia', [
        'empleado_id' => '',
        'fecha' => '',
        'hora_entrada' => '',
        'hora_salida' => '',
        'horas_trabajadas' => 0,
        'estado' => 'presente',
        'notas' => '',
    ]);

    $response->assertSessionHasNoErrors();
});
