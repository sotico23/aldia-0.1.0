<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        $nombres = ['Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Patricia', 'Miguel', 'Laura', 'Diego', 'Sofia', 'Roberto', 'Carmen', 'Fernando', 'Isabel', 'Alberto', 'Rosa', 'Jorge', 'Lucía', 'Eduardo', 'Marta'];
        $apellidos = ['García', 'Rodríguez', 'López', 'Martínez', 'González', 'Hernández', 'Pérez', 'Sánchez', 'Ramírez', 'Torres', 'Flores', 'Rivera', 'Gómez', 'Díaz', 'Reyes', 'Morales', 'Cruz', 'Ortiz', 'Gutierrez', 'Chávez'];

        return [
            'user_id' => null,
            'owner_id' => User::factory(),
            'nombre' => fake()->randomElement($nombres).' '.fake()->randomElement($apellidos),
            'nit' => fake()->unique()->numerify('#########-#'),
            'rut' => fake()->numerify('##.###.###-#'),
            'telefono' => fake()->numerify('+56 9 ########'),
            'email' => fake()->unique()->safeEmail(),
            'direccion' => fake()->streetAddress(),
            'ciudad' => fake()->city(),
            'region' => fake()->state(),
            'comuna' => fake()->citySuffix(),
            'giro' => fake()->randomElement(['Comercial', 'Industrial', 'Servicios', 'Tecnología', 'Alimentos', 'Construcción', 'Salud', 'Educación']),
            'contacto' => fake()->name(),
            'telefono_contacto' => fake()->numerify('+56 9 ########'),
            'categoria_id' => Categoria::where('tipo', 'cliente')->inRandomOrder()->first()?->id ?? null,
            'activo' => fake()->boolean(90),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
