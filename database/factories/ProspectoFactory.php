<?php

namespace Database\Factories;

use App\Models\Prospecto;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProspectoFactory extends Factory
{
    protected $model = Prospecto::class;

    public function definition(): array
    {
        $empresas = ['TechCorp', 'InnoSoft', 'DataPro', 'CloudNet', 'SmartSol', 'ByteWave', 'NetSecure', 'SoftEdge', 'WebPrime', 'InfoCore', 'DigitalHub', 'CyberLink', 'TechFlow', 'DataPulse', 'CloudBase'];

        return [
            'nombre' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('+56 9 ########'),
            'empresa' => fake()->randomElement($empresas).' '.fake()->numberBetween(1, 999),
            'descripcion' => fake()->paragraph(),
            'fuente' => fake()->randomElement(['Web', 'Referido', 'LinkedIn', 'Facebook', 'Google Ads', 'Expo', 'Recomendación']),
            'estado' => fake()->randomElement(['nuevo', 'contactado', 'calificado', 'propuesta', 'negociacion', 'ganado', 'perdido']),
            'valor_estimado' => fake()->randomFloat(2, 100000, 50000000),
            'fecha_seguimiento' => fake()->dateTimeBetween('now', '+30 days'),
            'notas' => fake()->optional()->paragraph(),
        ];
    }
}
