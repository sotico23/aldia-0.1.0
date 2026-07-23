<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['slug' => 'basico'], [
            'name' => 'Básico',
            'price' => 19990,
            'billing_cycle' => 'monthly',
            'active' => true,
            'features' => [
                'max_users' => 1,
                'max_products' => 50,
                'reports' => false,
                'api_access' => false,
            ],
        ]);

        Plan::updateOrCreate(['slug' => 'profesional'], [
            'name' => 'Profesional',
            'price' => 49990,
            'billing_cycle' => 'monthly',
            'active' => true,
            'features' => [
                'max_users' => 5,
                'max_products' => 500,
                'reports' => true,
                'api_access' => true,
            ],
        ]);

        Plan::updateOrCreate(['slug' => 'enterprise'], [
            'name' => 'Enterprise',
            'price' => 99990,
            'billing_cycle' => 'monthly',
            'active' => true,
            'features' => [
                'max_users' => 25,
                'max_products' => -1,
                'reports' => true,
                'api_access' => true,
            ],
        ]);

        Plan::updateOrCreate(['slug' => 'basico-anual'], [
            'name' => 'Básico Anual',
            'price' => 199990,
            'billing_cycle' => 'yearly',
            'active' => true,
            'features' => [
                'max_users' => 1,
                'max_products' => 50,
                'reports' => false,
                'api_access' => false,
            ],
        ]);

        Plan::updateOrCreate(['slug' => 'profesional-anual'], [
            'name' => 'Profesional Anual',
            'price' => 499990,
            'billing_cycle' => 'yearly',
            'active' => true,
            'features' => [
                'max_users' => 5,
                'max_products' => 500,
                'reports' => true,
                'api_access' => true,
            ],
        ]);
    }
}
