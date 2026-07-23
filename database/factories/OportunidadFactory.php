<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Oportunidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class OportunidadFactory extends Factory
{
    protected $model = Oportunidad::class;

    public function definition(): array
    {
        $nombres = [
            'Venta ERP', 'Licitación Pública', 'Contrato Mantenimiento',
            'Proyecto Desarrollo', 'Implementación Sistema', 'Consultoría',
            'Capacitación', 'Soporte Técnico', 'Licencia Software',
            'Integración API', 'Migración Datos', 'Auditoría',
        ];

        return [
            'nombre' => fake()->randomElement($nombres).' - '.fake()->company(),
            'cliente_id' => Cliente::factory(),

            'valor' => fake()->randomFloat(2, 500000, 100000000),
            'etapa' => fake()->randomElement(['prospeccion', 'calificacion', 'propuesta', 'negociacion', 'cierre']),
            'probabilidad' => fake()->numberBetween(10, 95),
            'fecha_cierre_estimada' => fake()->dateTimeBetween('now', '+6 months'),
            'descripcion' => fake()->paragraph(),
        ];
    }
}
