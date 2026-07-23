<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'client_id' => User::factory(),
            'provider_id' => User::factory(),
            'owner_id' => User::factory(),
            'producto_id' => Producto::factory(),
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'pendiente',
            'payment_status' => 'pendiente',
            'amount_paid' => 0,
        ];
    }
}
