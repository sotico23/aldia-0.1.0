<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nombre' => fake()->unique()->word().' '.fake()->numberBetween(1, 999),
            'descripcion' => fake()->sentence(),
            'tipo' => fake()->randomElement(['producto', 'cliente', 'proveedor', 'servicio']),
            'activo' => true,
            'mostrar_en_perfil' => fake()->boolean(70),
        ];
    }
}
