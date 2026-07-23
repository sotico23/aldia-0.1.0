<?php

namespace Database\Factories;

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmpleadoFactory extends Factory
{
    protected $model = Empleado::class;

    public function definition(): array
    {
        $cargos = ['Desarrollador', 'Diseñador', 'Analista', 'Gerente', 'Contador', 'Asistente', 'Técnico', 'Supervisor', 'Coordinador', 'Ejecutivo'];
        $departamentos = ['TI', 'Ventas', 'Marketing', 'RRHH', 'Finanzas', 'Operaciones', 'Producción', 'Logística', 'Administración', 'Compras'];

        return [
            'user_id' => User::factory(),
            'creator_id' => User::factory(),
            'almacen_id' => null,
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'email' => fake()->safeEmail().fake()->uuid().'@example.com',
            'telefono' => fake()->numerify('+56 9 ########'),
            'cargo' => fake()->randomElement($cargos),
            'departamento' => fake()->randomElement($departamentos),
            'fecha_contratacion' => fake()->dateTimeBetween('-5 years', 'now'),
            'salario' => fake()->randomFloat(2, 350000, 2500000),
            'estado' => fake()->randomElement(['activo', 'activo', 'activo', 'inactivo', 'vacaciones', 'licencia']),
            'direccion' => fake()->address(),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
