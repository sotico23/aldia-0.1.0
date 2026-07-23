<?php

namespace Tests;

use App\Models\Country;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Add trial_starts_at column for tests (SQLite in-memory)
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'trial_starts_at')) {
            Schema::table('users', function ($table) {
                $table->timestamp('trial_starts_at')->nullable()->after('trial_ends_at');
            });
        }

        $countries = [
            ['code' => 'CL', 'name' => 'Chile', 'currency_code' => 'CLP', 'currency_symbol' => '$', 'currency_decimals' => 0, 'locale' => 'es_CL', 'timezone' => 'America/Santiago', 'phone_code' => '+56', 'tax_name' => 'IVA', 'tax_rate' => 19, 'fiscal_id_label' => 'RUT', 'date_format' => 'd/m/Y', 'is_active' => true],
            ['code' => 'CO', 'name' => 'Colombia', 'currency_code' => 'COP', 'currency_symbol' => '$', 'currency_decimals' => 0, 'locale' => 'es_CO', 'timezone' => 'America/Bogota', 'phone_code' => '+57', 'tax_name' => 'IVA', 'tax_rate' => 19, 'fiscal_id_label' => 'NIT', 'date_format' => 'd/m/Y', 'is_active' => true],
            ['code' => 'PE', 'name' => 'Perú', 'currency_code' => 'PEN', 'currency_symbol' => 'S/', 'currency_decimals' => 2, 'locale' => 'es_PE', 'timezone' => 'America/Lima', 'phone_code' => '+51', 'tax_name' => 'IGV', 'tax_rate' => 18, 'fiscal_id_label' => 'RUC', 'date_format' => 'd/m/Y', 'is_active' => true],
            ['code' => 'AR', 'name' => 'Argentina', 'currency_code' => 'ARS', 'currency_symbol' => '$', 'currency_decimals' => 2, 'locale' => 'es_AR', 'timezone' => 'America/Argentina/Buenos_Aires', 'phone_code' => '+54', 'tax_name' => 'IVA', 'tax_rate' => 21, 'fiscal_id_label' => 'CUIT', 'date_format' => 'd/m/Y', 'is_active' => true],
            ['code' => 'BO', 'name' => 'Bolivia', 'currency_code' => 'BOB', 'currency_symbol' => 'Bs', 'currency_decimals' => 2, 'locale' => 'es_BO', 'timezone' => 'America/La_Paz', 'phone_code' => '+591', 'tax_name' => 'IT', 'tax_rate' => 13, 'fiscal_id_label' => 'NIT', 'date_format' => 'd/m/Y', 'is_active' => true],
            ['code' => 'US', 'name' => 'Estados Unidos', 'currency_code' => 'USD', 'currency_symbol' => '$', 'currency_decimals' => 2, 'locale' => 'en_US', 'timezone' => 'America/New_York', 'phone_code' => '+1', 'tax_name' => 'Sales Tax', 'tax_rate' => 0, 'fiscal_id_label' => 'EIN', 'date_format' => 'MM/DD/YYYY', 'is_active' => true],
            ['code' => 'BR', 'name' => 'Brasil', 'currency_code' => 'BRL', 'currency_symbol' => 'R$', 'currency_decimals' => 2, 'locale' => 'pt_BR', 'timezone' => 'America/Sao_Paulo', 'phone_code' => '+55', 'tax_name' => 'ICMS', 'tax_rate' => 17, 'fiscal_id_label' => 'CNPJ', 'date_format' => 'd/m/Y', 'is_active' => true],
            ['code' => 'VE', 'name' => 'Venezuela', 'currency_code' => 'VES', 'currency_symbol' => 'Bs', 'currency_decimals' => 2, 'locale' => 'es_VE', 'timezone' => 'America/Caracas', 'phone_code' => '+58', 'tax_name' => 'IVA', 'tax_rate' => 16, 'fiscal_id_label' => 'RIF', 'date_format' => 'd/m/Y', 'is_active' => true],
            ['code' => 'UY', 'name' => 'Uruguay', 'currency_code' => 'UYU', 'currency_symbol' => '$', 'currency_decimals' => 2, 'locale' => 'es_UY', 'timezone' => 'America/Montevideo', 'phone_code' => '+598', 'tax_name' => 'IVA', 'tax_rate' => 22, 'fiscal_id_label' => 'RUT', 'date_format' => 'd/m/Y', 'is_active' => true],
            ['code' => 'PY', 'name' => 'Paraguay', 'currency_code' => 'PYG', 'currency_symbol' => '₲', 'currency_decimals' => 0, 'locale' => 'es_PY', 'timezone' => 'America/Asuncion', 'phone_code' => '+595', 'tax_name' => 'IVA', 'tax_rate' => 10, 'fiscal_id_label' => 'RUC', 'date_format' => 'd/m/Y', 'is_active' => true],
            ['code' => 'GT', 'name' => 'Guatemala', 'currency_code' => 'GTQ', 'currency_symbol' => 'Q', 'currency_decimals' => 2, 'locale' => 'es_GT', 'timezone' => 'America/Guatemala', 'phone_code' => '+502', 'tax_name' => 'IVA', 'tax_rate' => 12, 'fiscal_id_label' => 'NIT', 'date_format' => 'd/m/Y', 'is_active' => true],
        ];

        foreach ($countries as $country) {
            Country::firstOrCreate(['code' => $country['code']], $country);
        }

        // Ensure default roles exist for tests since User model automatically assigns them
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Usuario', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Cliente', 'guard_name' => 'web']);
    }
}
