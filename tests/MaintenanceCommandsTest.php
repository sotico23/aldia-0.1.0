<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleRegionalSettings;
use App\Http\Middleware\TrackPageViews;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

uses(TestCase::class);

beforeEach(function () {
    $this->withoutMiddleware([HandleInertiaRequests::class, HandleRegionalSettings::class, TrackPageViews::class]);
    config()->set('cache.default', 'array');
    Cache::flush();
});

function maintenanceUser(string $role = 'Super Admin', bool $active = true): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => 901, 'is_active' => $active, 'email_verified_at' => now()]);
    $user->shouldReceive('hasRole')->andReturnUsing(fn (string $candidate): bool => $role === $candidate);
    $user->shouldReceive('can')->andReturn(true);
    $user->shouldReceive('isTrialActive')->andReturn(true);
    $user->shouldReceive('isCliente')->andReturn(false);
    $user->shouldReceive('isProveedor')->andReturn(false);

    return $user;
}

test('maintenance requires authentication', function () {
    Artisan::shouldReceive('call')->never();
    $this->getJson('/configuracion-web/mantenimiento')->assertUnauthorized();
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'route:clear'])->assertUnauthorized();
});

test('tenant administrators cannot access maintenance even with settings permissions', function () {
    $this->actingAs(maintenanceUser('Administrador'));
    Artisan::shouldReceive('call')->never();
    $this->getJson('/configuracion-web/mantenimiento')->assertForbidden();
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'route:clear'])->assertForbidden();
});

test('inactive administrators cannot execute maintenance', function () {
    $this->actingAs(maintenanceUser(active: false));
    Artisan::shouldReceive('call')->never();
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'route:clear'])->assertForbidden();
});

test('platform administrators can see commands and APK route status', function (string $role) {
    $this->actingAs(maintenanceUser($role));
    $this->withHeader('X-Inertia', 'true')->get('/configuracion-web/mantenimiento')
        ->assertOk()->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Backend/ConfiguracionWeb/Maintenance')
        ->assertJsonCount(15, 'props.commands')
        ->assertJsonPath('props.status.road_alerts_available', true)
        ->assertJsonMissingPath('props.commands.0.parameters');
})->with(['Master', 'Super Admin']);

test('only installed maintenance commands are offered', function () {
    expect(Artisan::all())->toHaveKeys(array_keys(config('maintenance.commands')));
});

test('executes a fixed command and records its actor and result', function () {
    $this->actingAs(maintenanceUser());
    Log::spy();
    Artisan::shouldReceive('call')->once()->with('route:clear', ['--no-interaction' => true], Mockery::type(BufferedOutput::class))
        ->andReturnUsing(function (string $command, array $parameters, BufferedOutput $output): int {
            $output->writeln('Routes cleared');

            return 0;
        });
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'route:clear'])
        ->assertOk()->assertJsonPath('success', true)->assertJsonPath('exit_code', 0)
        ->assertJsonPath('output', 'Routes cleared'.PHP_EOL);
    Log::shouldHaveReceived('notice')->once()->with('Mantenimiento web ejecutado', ['user_id' => 901, 'command' => 'route:clear', 'exit_code' => 0]);
});

test('rejects arbitrary commands and parameters', function (array $data, string $field) {
    $this->actingAs(maintenanceUser());
    Artisan::shouldReceive('call')->never();
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', $data)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['command' => 'migrate:fresh'], 'command'],
    [['command' => 'route:clear; rm -rf /'], 'command'],
    [['command' => ['route:clear']], 'command'],
    [['command' => 'route:clear', 'parameters' => ['--env' => 'other']], 'parameters'],
    [['command' => 'route:clear', 'arguments' => 'anything'], 'arguments'],
]);

test('requires explicit confirmation for database migrations', function () {
    $this->actingAs(maintenanceUser());
    Artisan::shouldReceive('call')->never();
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'migrate'])
        ->assertUnprocessable()->assertJsonValidationErrors('confirmed');
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'migrate', 'confirmed' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('confirmed');
});

test('confirmed migrations use server controlled options', function () {
    $this->actingAs(maintenanceUser());
    Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true, '--no-interaction' => true], Mockery::type(BufferedOutput::class))->andReturn(0);
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'migrate', 'confirmed' => true])
        ->assertOk()->assertJsonPath('success', true);
});

test('reports nonzero command exit codes', function () {
    $this->actingAs(maintenanceUser());
    Artisan::shouldReceive('call')->once()->andReturn(1);
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'route:cache'])
        ->assertOk()->assertJsonPath('success', false)->assertJsonPath('exit_code', 1);
});

test('refuses concurrent maintenance and releases the lock after completion', function () {
    $this->actingAs(maintenanceUser());
    $lock = Cache::build(['driver' => 'file', 'path' => storage_path('framework/maintenance-locks'), 'lock_path' => storage_path('framework/maintenance-locks')])
        ->lock('artisan-command', 900);
    expect($lock->get())->toBeTrue();
    Artisan::shouldReceive('call')->once()->andReturn(0);
    try {
        $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'route:clear'])->assertConflict();
    } finally {
        $lock->release();
    }
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'route:clear'])->assertOk();
});

test('command exceptions do not expose secrets and permit retrying', function () {
    $this->actingAs(maintenanceUser());
    Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('secret-value-123'));
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'route:clear'])
        ->assertOk()->assertJsonPath('success', false)->assertDontSee('secret-value-123');
    Artisan::shouldReceive('call')->once()->andReturn(0);
    $this->postJson('/configuracion-web/mantenimiento/ejecutar', ['command' => 'route:clear'])
        ->assertOk()->assertJsonPath('success', true);
});
