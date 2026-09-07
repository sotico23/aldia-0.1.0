<?php

namespace Database\Factories;

use App\Models\DeliveryAsignacion;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAsignacion>
 */
class DeliveryAsignacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'pedido_id' => Pedido::factory(),
            'repartidor_id' => User::factory(),
            'zona_id' => null,
            'asignador_id' => null,
            'motivo' => 'auto_asignado',
        ];
    }
}
