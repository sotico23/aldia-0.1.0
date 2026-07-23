<?php

namespace Database\Factories;

use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimesheetFactory extends Factory
{
    protected $model = Timesheet::class;

    public function definition(): array
    {
        return [
            'proyecto' => fake()->randomElement(['ERP', 'Web', 'App Móvil', 'CRM', 'Internal']),
            'empleado' => 'Empleado '.fake()->numberBetween(1, 50),
            'fecha' => fake()->dateTimeBetween('-1 month', 'now'),
            'horas' => fake()->randomFloat(2, 1, 12),
            'descripcion' => fake()->sentence(),
            'estado' => fake()->randomElement(['aprobado', 'pendiente', 'rechazado']),
        ];
    }
}
