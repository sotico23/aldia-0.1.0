<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    public function definition(): array
    {
        $nombres = ['Distribuidora', 'Importadora', 'Comercial', 'Industrial', 'Import & Export'];
        $rubros = ['Electrónica', 'Tecnología', 'Alimentos', 'Papelería', 'Ferretería', 'Textil', 'Químicos', 'Muebles'];

        return [
            'user_id' => User::factory(),
            'owner_id' => User::factory(),
            'nombre' => fake()->randomElement($nombres).' '.fake()->company(),
            'nit' => fake()->unique()->numerify('#########-#'),
            'telefono' => fake()->numerify('+56 2 ########'),
            'email' => fake()->unique()->safeEmail(),
            'direccion' => fake()->streetAddress().', '.fake()->city(),
            'categoria_id' => Categoria::where('tipo', 'proveedor')->inRandomOrder()->first()?->id ?? null,
            'activo' => true,
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
