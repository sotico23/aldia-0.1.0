<?php

namespace Database\Factories;

use App\Models\WebSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebSettingFactory extends Factory
{
    protected $model = WebSetting::class;

    public function definition(): array
    {
        return [
            'app_name' => 'GrowERP',
            'app_title' => 'GrowERP - Tu ERP todo-en-uno',
            'timezone' => 'UTC',
            'locale' => 'es_CL',
            'currency' => 'CLP',
            'currency_symbol' => '$',
        ];
    }
}
