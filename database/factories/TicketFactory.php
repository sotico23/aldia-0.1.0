<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'titulo' => fake()->sentence(4),
            'descripcion' => fake()->paragraph(),
            'categoria' => fake()->randomElement(['soporte', 'bug', 'consulta', 'incidente']),
            'prioridad' => fake()->randomElement(['baja', 'media', 'alta', 'urgente']),
            'estado' => fake()->randomElement(['abierto', 'en_progreso', 'resuelto', 'cerrado']),
            'cliente_nombre' => fake()->name(),
        ];
    }
}
