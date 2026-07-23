<?php

namespace Database\Factories;

use App\Models\Asistencia;
use App\Models\Empleado;
use Illuminate\Database\Eloquent\Factories\Factory;

class AsistenciaFactory extends Factory
{
    protected $model = Asistencia::class;

    public function definition(): array
    {
        return [
            'empleado_id' => Empleado::factory(),
            'fecha' => fake()->dateTimeBetween('-1 month', 'now'),
            'hora_entrada' => fake()->time('H:i:s'),
            'hora_salida' => fake()->optional()->time('H:i:s'),
            'estado' => fake()->randomElement(['presente', 'ausente', 'vacaciones', 'licencia', 'teletrabajo']),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
