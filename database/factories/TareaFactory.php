<?php

namespace Database\Factories;

use App\Models\Tarea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TareaFactory extends Factory
{
    protected $model = Tarea::class;

    public function definition(): array
    {
        $titulos = [
            'Revisar inventario', 'Actualizar base de datos', 'Preparar informe mensual',
            'Contactar proveedores', 'Revisar cotizaciones', 'Actualizar precios',
            'Atender cliente', 'Resolver ticket', 'Revisar órdenes de producción',
            'Coordinar entrega', 'Actualizar documentación', 'Revisarube tareas pendientes',
            'Preparar reunión', 'Seguimiento a proyecto', 'Facturación',
        ];

        return [
            'user_id' => User::factory(),
            'empleado_id' => User::factory(),
            'titulo' => fake()->randomElement($titulos).' - '.fake()->numberBetween(1, 100),
            'descripcion' => fake()->paragraph(),
            'estado' => fake()->randomElement(['pendiente', 'en_progreso', 'completada', 'cancelada']),
            'prioridad' => fake()->randomElement(['baja', 'media', 'alta', 'urgente']),
            'fecha_limite' => fake()->dateTimeBetween('now', '+30 days'),
            'productos_json' => json_encode([]),
        ];
    }
}
