<?php

namespace Database\Factories;

use App\Models\Bom;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomFactory extends Factory
{
    protected $model = Bom::class;

    public function definition(): array
    {
        return [

            'nombre' => 'BOM '.fake()->numerify('####'),
            'producto_final' => 'Producto Terminado '.fake()->numberBetween(1000, 9999),
            'cantidad' => fake()->numberBetween(1, 100),
            'materiales' => json_encode([
                ['producto_id' => Producto::factory(), 'cantidad' => fake()->numberBetween(1, 10)],
                ['producto_id' => Producto::factory(), 'cantidad' => fake()->numberBetween(1, 10)],
            ]),
            'activo' => true,
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
