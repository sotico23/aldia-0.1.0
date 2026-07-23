<?php

namespace Database\Factories;

use App\Models\Almacen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlmacenFactory extends Factory
{
    protected $model = Almacen::class;

    public function definition(): array
    {
        $nombres = ['Central', 'Principal', 'Secundario', 'Distribución', 'Bodega'];

        return [
            'user_id' => User::factory(),
            'nombre' => fake()->randomElement($nombres).' '.fake()->city(),
            'codigo' => fake()->unique()->numerify('ALM-###-').fake()->randomLetter().fake()->randomLetter(),
            'direccion' => fake()->streetAddress().', '.fake()->city(),
            'telefono' => fake()->numerify('+56 2 ########'),
            'responsable' => fake()->name(),
            'capacidad' => fake()->numberBetween(100, 10000),
            'tipo' => fake()->randomElement(['principal', 'secundario', 'distribucion', 'produccion']),
            'activo' => true,
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
