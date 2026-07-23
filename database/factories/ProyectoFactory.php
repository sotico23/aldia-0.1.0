<?php

namespace Database\Factories;

use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProyectoFactory extends Factory
{
    protected $model = Proyecto::class;

    public function definition(): array
    {
        $estados = ['planificacion', 'en_progreso', 'en_progreso', 'en_progreso', 'pausado', 'completado', 'cancelado'];
        $nombres = [
            'Sistema de Gestión ERP', 'Portal Web Corporativo', 'App Móvil de Delivery',
            'Plataforma E-Learning', 'CRM Empresarial', 'Sistema de Inventario',
            'Dashboard Analytics', 'Módulo de Facturación', 'Sistema de RRHH',
            'Plataforma de Pagos', 'App de Reservas', 'Sistema de Tickets',
            'Portal de Clientes', 'Sistema de Nómina', 'Módulo de Logística',
        ];

        return [
            'nombre' => fake()->randomElement($nombres).' - '.fake()->company(),
            'descripcion' => fake()->paragraph(),
            'cliente' => fake()->company(),
            'responsable' => fake()->name(),
            'fecha_inicio' => fake()->dateTimeBetween('-6 months', 'now'),
            'fecha_fin' => fake()->dateTimeBetween('now', '+6 months'),
            'presupuesto' => fake()->randomFloat(2, 1000000, 50000000),
            'gasto_real' => fake()->randomFloat(2, 0, 30000000),
            'progreso' => fake()->numberBetween(0, 100),
            'estado' => fake()->randomElement($estados),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
