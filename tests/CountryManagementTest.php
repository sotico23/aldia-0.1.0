<?php

use App\Http\Middleware\CheckActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleRegionalSettings;
use App\Http\Middleware\RedirectIfClient;
use App\Http\Middleware\RedirectIfProveedor;
use App\Http\Middleware\TrackPageViews;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

function managementCountryData(array $overrides = []): array
{
    return array_replace([
        'code' => 'CL', 'name' => 'Chile', 'currency_code' => 'CLP', 'currency_symbol' => '$',
        'currency_decimals' => 0, 'locale' => 'es-CL', 'timezone' => 'America/Santiago',
        'phone_code' => '+56', 'tax_name' => 'IVA', 'tax_rate' => 19,
        'fiscal_id_label' => 'RUT', 'fiscal_id_pattern' => null,
        'date_format' => 'DD/MM/YYYY', 'is_active' => true,
    ], $overrides);
}

beforeEach(function () {
    $migration = require database_path('migrations/2026_07_10_000000_create_countries_table.php');
    $migration->up();
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('country')->nullable();
    });
    $this->withoutMiddleware([
        HandleInertiaRequests::class,
        HandleRegionalSettings::class,
        TrackPageViews::class,
        CheckActive::class,
        RedirectIfClient::class,
        RedirectIfProveedor::class,
    ]);
    $this->manager = Mockery::mock(User::class)->makePartial();
    $this->manager->forceFill(['id' => 100, 'email_verified_at' => now()]);
    $this->manager->shouldReceive('hasRole')->andReturn(true);
    $this->actingAs($this->manager);
});

afterEach(function () {
    Schema::dropIfExists('users');
    Schema::dropIfExists('countries');
});

test('crear pais normaliza los codigos y vuelve a configuracion', function () {
    $this->from('/configuracion-web')->post(route('paises.store'), managementCountryData(['code' => 'cl', 'currency_code' => 'clp']))
        ->assertRedirect('/configuracion-web')->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'País creado correctamente.');
    $this->assertDatabaseHas('countries', ['code' => 'CL', 'currency_code' => 'CLP', 'is_active' => true]);
});

test('un pais nuevo activo aparece inmediatamente en el registro y desaparece al desactivarlo', function () {
    $this->from('/configuracion-web')->post(route('paises.store'), managementCountryData([
        'code' => 'VE', 'name' => 'Venezuela', 'currency_code' => 'VES',
        'currency_symbol' => 'Bs', 'currency_decimals' => 2, 'locale' => 'es-VE',
        'timezone' => 'America/Caracas', 'phone_code' => '+58', 'tax_rate' => 16,
        'fiscal_id_label' => 'RIF', 'is_active' => true,
    ]))->assertSessionHasNoErrors()->assertRedirect('/configuracion-web');

    auth()->guard()->forgetUser();
    $this->withHeader('X-Inertia', 'true')->get('/register')->assertSuccessful()
        ->assertJsonPath('props.countries.0.code', 'VE')
        ->assertJsonPath('props.countries.0.currency_code', 'VES');

    $this->actingAs($this->manager);
    $country = Country::where('code', 'VE')->firstOrFail();
    $this->patch(route('paises.toggle', $country))->assertRedirect();
    auth()->guard()->forgetUser();
    $this->withHeader('X-Inertia', 'true')->get('/register')->assertSuccessful()
        ->assertJsonCount(0, 'props.countries');
});

test('editar y eliminar resuelven el pais correcto por la ruta', function () {
    $country = Country::create(managementCountryData());
    $this->from('/configuracion-web')->put(route('paises.update', $country), managementCountryData(['name' => 'Chile actualizado', 'tax_rate' => 20]))
        ->assertRedirect('/configuracion-web')->assertSessionHasNoErrors();
    $this->assertDatabaseHas('countries', ['id' => $country->id, 'name' => 'Chile actualizado', 'tax_rate' => 20]);
    $this->delete(route('paises.destroy', $country))->assertRedirect();
    $this->assertDatabaseMissing('countries', ['id' => $country->id]);
});

test('el interruptor activa y desactiva el pais', function () {
    $country = Country::create(managementCountryData());
    $this->patch(route('paises.toggle', $country))->assertRedirect();
    expect($country->fresh()->is_active)->toBeFalse();
    $this->patch(route('paises.toggle', $country))->assertRedirect();
    expect($country->fresh()->is_active)->toBeTrue();
});

test('no permite borrar paises utilizados por usuarios', function () {
    $country = Country::create(managementCountryData());
    DB::table('users')->insert(['country' => 'CL']);
    $this->from('/configuracion-web')->delete(route('paises.destroy', $country))->assertSessionHasErrors('country');
    $this->assertDatabaseHas('countries', ['id' => $country->id]);
});

test('valida duplicados codigos y limites antes de guardar', function (array $overrides, string $field) {
    Country::create(managementCountryData());
    $this->post(route('paises.store'), managementCountryData($overrides))
        ->assertSessionHasErrors($field);
    $this->assertDatabaseCount('countries', 1);
})->with([
    'duplicado minusculas' => [['code' => 'cl'], 'code'],
    'codigo incompleto' => [['code' => 'C'], 'code'],
    'moneda incompleta' => [['code' => 'PE', 'currency_code' => 'PE'], 'currency_code'],
    'zona invalida' => [['code' => 'PE', 'timezone' => 'Planeta/Marte'], 'timezone'],
    'decimales invalidos' => [['code' => 'PE', 'currency_decimals' => 9], 'currency_decimals'],
    'impuesto invalido' => [['code' => 'PE', 'tax_rate' => 110], 'tax_rate'],
]);

test('deniega las operaciones de paises sin permiso de escritura', function () {
    $reader = Mockery::mock(User::class)->makePartial();
    $reader->forceFill(['id' => 101, 'email_verified_at' => now()]);
    $reader->shouldReceive('hasRole')->andReturn(false);
    $reader->shouldReceive('can')->andReturnUsing(fn (string $permission): bool => str_ends_with($permission, '.viewAny'));
    $reader->shouldReceive('isTrialActive')->andReturn(true);
    $this->actingAs($reader);
    $country = Country::create(managementCountryData());
    $this->post(route('paises.store'), managementCountryData(['code' => 'PE']))->assertForbidden();
    $this->put(route('paises.update', $country), managementCountryData())->assertForbidden();
    $this->patch(route('paises.toggle', $country))->assertForbidden();
    $this->delete(route('paises.destroy', $country))->assertForbidden();
});
