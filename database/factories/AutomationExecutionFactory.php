<?php

namespace Database\Factories;

use App\Models\AutomationExecution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationExecutionFactory extends Factory
{
    protected $model = AutomationExecution::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'workflow' => 'automation',
            'status' => 'success',
            'triggered_by' => 'scheduler',
            'execution_time_ms' => fake()->numberBetween(100, 5000),
            'executed_at' => now(),
        ];
    }
}
