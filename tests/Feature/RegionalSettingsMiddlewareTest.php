<?php

use App\Http\Middleware\HandleRegionalSettings;
use App\Models\Country;
use App\Models\User;
use App\Services\GeolocationService;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CountrySeeder::class);
});

describe('GeolocationService', function () {
    it('returns null for localhost IP', function () {
        $service = new GeolocationService;
        expect($service->resolveCountryFromIp('127.0.0.1'))->toBeNull();
        expect($service->resolveCountryFromIp('::1'))->toBeNull();
    });

    it('returns null for null IP', function () {
        $service = new GeolocationService;
        expect($service->resolveCountryFromIp(null))->toBeNull();
    });

    it('returns country code from API for valid IP', function () {
        Http::fake([
            'http://ip-api.com/json/181.47.100.10?fields=countryCode' => Http::response([
                'countryCode' => 'PE',
            ], 200),
        ]);

        $service = new GeolocationService;
        expect($service->resolveCountryFromIp('181.47.100.10'))->toBe('PE');
    });

    it('returns null when API fails', function () {
        Http::fake([
            'http://ip-api.com/json/*' => Http::response(null, 500),
        ]);

        $service = new GeolocationService;
        expect($service->resolveCountryFromIp('181.47.100.10'))->toBeNull();
    });

    it('caches geolocation results', function () {
        Http::fake([
            'http://ip-api.com/json/181.47.100.10?fields=countryCode' => Http::response([
                'countryCode' => 'CL',
            ], 200),
        ]);

        $service = new GeolocationService;
        $service->resolveCountryFromIp('181.47.100.10');
        $service->resolveCountryFromIp('181.47.100.10');

        // Should only make one HTTP request
        Http::assertSentCount(1);
    });

    it('returns null for unsupported countries', function () {
        Http::fake([
            'http://ip-api.com/json/8.8.8.8?fields=countryCode' => Http::response([
                'countryCode' => 'XX',
            ], 200),
        ]);

        $service = new GeolocationService;
        expect($service->resolveCountryFromIp('8.8.8.8'))->toBeNull();
    });

    it('normalizes country code to uppercase', function () {
        Http::fake([
            'http://ip-api.com/json/181.47.100.10?fields=countryCode' => Http::response([
                'countryCode' => 'pe',
            ], 200),
        ]);

        $service = new GeolocationService;
        expect($service->resolveCountryFromIp('181.47.100.10'))->toBe('PE');
    });
});

describe('HandleRegionalSettings Middleware', function () {
    it('attaches country settings from user country', function () {
        $user = User::factory()->peru()->create();
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new HandleRegionalSettings(new GeolocationService);
        $response = null;
        $middleware->handle($request, function (Request $r) use (&$response) {
            $country = $r->attributes->get('country_settings');
            expect($country)->toBeInstanceOf(Country::class)
                ->and($country->code)->toBe('PE');
            $response = new Response;

            return $response;
        });

        expect($response)->not->toBeNull();
    });

    it('falls back to default country for guest users', function () {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => null);

        $middleware = new HandleRegionalSettings(new GeolocationService);
        $middleware->handle($request, function (Request $r) {
            $country = $r->attributes->get('country_settings');
            expect($country->code)->toBe('CL');

            return new Response;
        });
    });
});

describe('Inertia Country Settings', function () {
    it('country_settings is shared via Inertia for authenticated user', function () {
        $user = User::factory()->peru()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->has('country_settings')
            ->where('country_settings.code', 'PE')
            ->where('country_settings.currency.code', 'PEN')
            ->where('country_settings.tax.name', 'IGV')
        );
    });

    it('country_settings contains correct Chile data', function () {
        $user = User::factory()->chile()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->has('country_settings')
            ->where('country_settings.code', 'CL')
            ->where('country_settings.currency.code', 'CLP')
            ->where('country_settings.currency.symbol', '$')
            ->where('country_settings.tax.name', 'IVA')
            ->where('country_settings.tax.rate', 19)
        );
    });

    it('country_settings includes all required fields', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->has('country_settings')
            ->has('country_settings.code')
            ->has('country_settings.name')
            ->has('country_settings.currency')
            ->has('country_settings.currency.code')
            ->has('country_settings.currency.symbol')
            ->has('country_settings.currency.decimals')
            ->has('country_settings.currency.locale')
            ->has('country_settings.timezone')
            ->has('country_settings.locale')
            ->has('country_settings.tax')
            ->has('country_settings.tax.name')
            ->has('country_settings.tax.rate')
            ->has('country_settings.fiscal_id')
            ->has('country_settings.fiscal_id.label')
            ->has('country_settings.phone_code')
        );
    });
});
