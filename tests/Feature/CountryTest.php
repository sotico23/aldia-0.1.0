<?php

use App\Enums\Currency;
use App\Helpers\MoneyHelper;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CountrySeeder::class);
});

describe('Country Model', function () {
    it('finds country by code', function () {
        $country = Country::findByCode('CL');
        expect($country)->not->toBeNull()
            ->and($country->name)->toBe('Chile')
            ->and($country->currency_code)->toBe('CLP');
    });

    it('finds Peru by code', function () {
        $country = Country::findByCode('PE');
        expect($country)->not->toBeNull()
            ->and($country->name)->toBe('Perú')
            ->and($country->currency_code)->toBe('PEN');
    });

    it('returns null for unknown code', function () {
        $country = Country::findByCode('XX');
        expect($country)->toBeNull();
    });

    it('gets active countries', function () {
        $active = Country::getActive();
        expect($active)->toHaveCount(11);
    });

    it('gets default country (Chile)', function () {
        $default = Country::getDefault();
        expect($default->code)->toBe('CL');
    });

    it('has correct attributes for Chile', function () {
        $cl = Country::findByCode('CL');
        expect($cl->currency_symbol)->toBe('$')
            ->and($cl->currency_decimals)->toBe(0)
            ->and($cl->locale)->toBe('es-CL')
            ->and($cl->timezone)->toBe('America/Santiago')
            ->and($cl->tax_name)->toBe('IVA')
            ->and((float) $cl->tax_rate)->toBe(19.0)
            ->and($cl->fiscal_id_label)->toBe('RUT')
            ->and($cl->phone_code)->toBe('+56');
    });

    it('has correct attributes for Peru', function () {
        $pe = Country::findByCode('PE');
        expect($pe->currency_symbol)->toBe('S/')
            ->and($pe->currency_decimals)->toBe(2)
            ->and($pe->locale)->toBe('es-PE')
            ->and($pe->timezone)->toBe('America/Lima')
            ->and($pe->tax_name)->toBe('IGV')
            ->and((float) $pe->tax_rate)->toBe(18.0)
            ->and($pe->fiscal_id_label)->toBe('RUC')
            ->and($pe->phone_code)->toBe('+51');
    });
});

describe('User Country Relationship', function () {
    it('user has country relationship', function () {
        $user = User::factory()->chile()->create();
        $user->load('countryModel');
        expect($user->countryModel)->toBeInstanceOf(Country::class)
            ->and($user->countryModel->code)->toBe('CL');
    });

    it('user defaults to Chile', function () {
        $user = User::factory()->create();
        expect($user->country)->toBe('CL');
    });

    it('user can be from Peru', function () {
        $user = User::factory()->peru()->create();
        expect($user->country)->toBe('PE');
        $user->load('countryModel');
        expect($user->countryModel->currency_code)->toBe('PEN');
    });

    it('getCountryConfig returns country model', function () {
        $user = User::factory()->peru()->create();
        $config = $user->getCountryConfig();
        expect($config->code)->toBe('PE');
    });

    it('getCountryConfig falls back to default for null country', function () {
        $user = User::factory()->create(['country' => null]);
        $config = $user->getCountryConfig();
        expect($config->code)->toBe('CL');
    });
});

describe('Currency fromCountry', function () {
    it('returns PEN for Peru', function () {
        $currency = Currency::fromCountry('PE');
        expect($currency)->toBe(Currency::PEN);
    });

    it('returns CLP for Chile', function () {
        $currency = Currency::fromCountry('CL');
        expect($currency)->toBe(Currency::CLP);
    });

    it('returns CLP as default for unknown country', function () {
        $currency = Currency::fromCountry('XX');
        expect($currency)->toBe(Currency::CLP);
    });
});

describe('MoneyHelper for Country', function () {
    it('formats Chilean peso correctly', function () {
        $formatted = MoneyHelper::formatForCountry(1500, 'CL');
        expect($formatted)->toBe('$ 1.500');
    });

    it('formats Peruvian sol correctly', function () {
        $formatted = MoneyHelper::formatForCountry(1500, 'PE');
        // number_format uses Spanish locale: . for thousands, , for decimals
        expect($formatted)->toBe('S/ 1.500,00');
    });

    it('formats zero amount for Chile', function () {
        $formatted = MoneyHelper::formatForCountry(0, 'CL');
        expect($formatted)->toBe('$ 0');
    });

    it('formats zero amount for Peru', function () {
        $formatted = MoneyHelper::formatForCountry(0, 'PE');
        expect($formatted)->toBe('S/ 0,00');
    });

    it('formats large amount for Chile', function () {
        $formatted = MoneyHelper::formatForCountry(1500000, 'CL');
        expect($formatted)->toBe('$ 1.500.000');
    });

    it('formats large amount for Peru', function () {
        $formatted = MoneyHelper::formatForCountry(1500000, 'PE');
        // number_format uses Spanish locale: . for thousands, , for decimals
        expect($formatted)->toBe('S/ 1.500.000,00');
    });

    it('falls back to default country when null', function () {
        $formatted = MoneyHelper::formatForCountry(1500, null);
        expect($formatted)->toContain('$');
    });
});
