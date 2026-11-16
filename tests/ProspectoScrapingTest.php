<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\ScrapeGoogleMapsProspectos;
use App\Models\Prospecto;
use App\Models\ProspectoScrapeRun;
use App\Models\User;
use App\Services\ProspectoScrapingService;
use App\Support\SystemSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');
    config()->set('cache.default', 'array');
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 300);
    Cache::flush();
    $this->withoutMiddleware([ValidateCsrfToken::class, HandleInertiaRequests::class]);
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->unsignedBigInteger('creator_id')->nullable();
        $table->string('country')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->rememberToken();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('banned_at')->nullable();
        $table->boolean('has_explicit_role')->default(true);
        $table->timestamps();
    });
    (require database_path('migrations/2026_03_08_214259_create_permission_tables.php'))->up();
    (require database_path('migrations/2026_05_31_210237_add_owner_id_to_roles_table.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Schema::create('settings', function (Blueprint $table): void {
        $table->id();
        $table->string('key')->unique();
        $table->text('value')->nullable();
        $table->string('group')->nullable();
        $table->boolean('is_encrypted')->default(false);
        $table->timestamps();
    });
    (require database_path('migrations/2026_03_01_020807_create_prospectos_table.php'))->up();
    Schema::table('prospectos', function (Blueprint $table): void {
        $table->foreignId('owner_id')->nullable()->constrained('users');
        $table->string('direccion')->nullable();
        $table->string('prioridad')->nullable();
        $table->string('giro')->nullable();
    });
    (require database_path('migrations/2026_11_15_171941_create_prospecto_scrape_runs_table.php'))->up();
    (require database_path('migrations/2026_11_15_171942_add_google_maps_fields_to_prospectos_table.php'))->up();
    $this->owner = User::withoutEvents(fn () => User::factory()->create(['id' => 10]));
    $this->owner->assignRole(Role::findOrCreate('Master', 'web'));
    $this->other = User::withoutEvents(fn () => User::factory()->create(['id' => 20]));
    foreach (['comercial.prospectos.viewAny', 'comercial.prospectos.import', 'comercial.prospectos.create'] as $permission) {
        $this->other->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($this->owner);
    SystemSettings::save(['SCRAPING_CHROME_BINARY' => ['value' => '/test/chromium', 'group' => 'prospecting']]);
    Process::fake(fn ($process) => Process::result(output: str_contains(is_array($process->command) ? implode(' ', $process->command) : $process->command, '--version') ? 'v26.0.0' : json_encode(scrapingEnginePayload())));
});

function scrapingEnginePayload(): array
{
    return ['status' => 'completed', 'results' => [['name' => 'Panadería Central', 'address' => 'Calle 123',
        'phone' => '+56912345678', 'category' => 'Panadería', 'website' => 'https://panaderia.example',
        'maps_url' => 'https://www.google.com/maps/place/Panaderia/data=!1snegocio-123', 'place_id' => 'negocio-123']]];
}

function scrapingRun(array $attributes = []): ProspectoScrapeRun
{
    return ProspectoScrapeRun::factory()->create(['owner_id' => 10, 'user_id' => 10, ...$attributes]);
}

test('encola una busqueda propia y rechaza iniciar otra o superar el limite', function () {
    Bus::fake();
    $input = ['query' => 'Panaderías', 'location' => 'Santiago, Chile', 'max_results' => 10, 'owner_id' => 20];
    $this->post('/prospectos/google-maps/buscar', $input)->assertRedirect();
    $run = ProspectoScrapeRun::firstOrFail();
    expect($run->owner_id)->toBe(10)->and($run->user_id)->toBe(10)->and($run->status)->toBe('queued');
    Bus::assertDispatched(ScrapeGoogleMapsProspectos::class, fn ($job) => $job->runId === $run->id && $job->queue === 'prospecting');
    $this->post('/prospectos/google-maps/buscar', $input)->assertSessionHasErrors('query');
    $this->post('/prospectos/google-maps/buscar', [...$input, 'max_results' => 100])->assertSessionHasErrors('max_results');
    expect(ProspectoScrapeRun::count())->toBe(1);
});

test('el worker normaliza resultados y repetir el trabajo no ejecuta de nuevo el navegador', function () {
    $run = scrapingRun();
    $job = new ScrapeGoogleMapsProspectos($run->id);
    $job->handle(app(ProspectoScrapingService::class));
    expect($run->fresh()->status)->toBe('completed')->and($run->fresh()->results[0]['name'])->toBe('Panadería Central');
    $job->handle(app(ProspectoScrapingService::class));
    Process::assertRanTimes(fn (): bool => true, 1);
});

test('importar seleccionados crea prospectos nuevos y omite duplicados dentro de la empresa', function () {
    $run = scrapingRun();
    (new ScrapeGoogleMapsProspectos($run->id))->handle(app(ProspectoScrapingService::class));
    $run->refresh();
    $ids = array_column($run->results, 'id');
    $this->post("/prospectos/google-maps/{$run->id}/importar", ['ids' => $ids, 'owner_id' => 20])->assertRedirect();
    $this->post("/prospectos/google-maps/{$run->id}/importar", ['ids' => $ids])->assertRedirect();
    expect(Prospecto::count())->toBe(1);
    $prospect = Prospecto::firstOrFail();
    expect($prospect->estado)->toBe('nuevo')->and($prospect->fuente)->toBe('google_maps')->and($prospect->owner_id)->toBe(10)
        ->and($prospect->sitio_web)->toBe('https://panaderia.example');
    $otherRun = scrapingRun(['owner_id' => 20, 'user_id' => 20, 'status' => 'completed', 'results' => $run->results]);
    $this->actingAs($this->other)->post("/prospectos/google-maps/{$otherRun->id}/importar", ['ids' => $ids])->assertRedirect();
    expect(Prospecto::withoutGlobalScopes()->count())->toBe(2);
});

test('no permite leer ni importar ni cancelar resultados ajenos incluso con rol Master', function () {
    $run = scrapingRun(['owner_id' => 20, 'user_id' => 20, 'status' => 'completed', 'results' => []]);
    expect(app(ProspectoScrapingService::class)->state(10)['runs'])->toBe([]);
    $this->post("/prospectos/google-maps/{$run->id}/importar", ['ids' => [str_repeat('a', 64)]])->assertNotFound();
    $this->post("/prospectos/google-maps/{$run->id}/cancelar")->assertNotFound();
});

test('solo un administrador puede cambiar los ejecutables del servidor', function () {
    $this->actingAs($this->other)->patch('/prospectos/google-maps/configuracion', ['node_binary' => '/bin/otro'])->assertForbidden();
    $state = app(ProspectoScrapingService::class)->state(20);
    expect($state['binaries'])->toBeNull();
    $this->actingAs($this->owner)->patch('/prospectos/google-maps/configuracion', ['node_binary' => 'node', 'chrome_binary' => '/srv/chromium'])->assertRedirect();
    expect(SystemSettings::get('SCRAPING_CHROME_BINARY'))->toBe('/srv/chromium');
});

test('sin cola asincrona rechaza la busqueda en lugar de bloquear la pagina', function () {
    config()->set('queue.default', 'sync');
    Bus::fake();
    $this->post('/prospectos/google-maps/buscar', ['query' => 'Panadería', 'location' => 'Chile', 'max_results' => 2])->assertSessionHasErrors('query');
    Bus::assertNothingDispatched();
});

test('una reserva menor que el minimo no habilita el motor y se puede configurar manualmente', function () {
    config()->set('queue.connections.database.retry_after', 90);
    expect(app(ProspectoScrapingService::class)->health()['queue_ready'])->toBeFalse();
    $this->patch('/prospectos/google-maps/configuracion', ['node_binary' => 'node', 'chrome_binary' => '/test/chromium', 'queue_connection' => 'database', 'queue_retry_after' => 420])
        ->assertRedirect()->assertSessionHas('success', fn (string $message): bool => str_contains($message, '420 segundos'));
    config()->set('queue.connections.database.retry_after', 90);
    expect(SystemSettings::get('DB_QUEUE_RETRY_AFTER'))->toBe('420')
        ->and(app(ProspectoScrapingService::class)->health()['queue_retry_after'])->toBe(420)
        ->and(app(ProspectoScrapingService::class)->health()['queue_ready'])->toBeTrue();
});

test('no permite reservar la cola por menos de 300 segundos', function () {
    $this->patch('/prospectos/google-maps/configuracion', [
        'node_binary' => 'node', 'chrome_binary' => '/test/chromium',
        'queue_connection' => 'database', 'queue_retry_after' => 299,
    ])->assertSessionHasErrors('queue_retry_after');
});

test('guardar vuelve a probar los ejecutables aunque haya un fallo previo en cache', function () {
    Process::fake(['*' => Process::result(exitCode: 1)]);
    $engine = app(ProspectoScrapingService::class);
    expect($engine->health()['ready'])->toBeFalse();
    Process::fake(['*' => Process::result(output: 'v26.0.0')]);
    expect($engine->health()['ready'])->toBeFalse();
    $this->patch('/prospectos/google-maps/configuracion', ['node_binary' => 'node', 'chrome_binary' => '/test/chromium', 'queue_connection' => 'database'])
        ->assertRedirect()->assertSessionHas('success');
    expect($engine->health()['ready'])->toBeTrue()->and($engine->health()['queue_ready'])->toBeTrue();
});

test('comprobar ejecuta de nuevo la verificacion y distingue un navegador faltante', function () {
    $engine = app(ProspectoScrapingService::class);
    expect($engine->health()['ready'])->toBeTrue();
    Process::fake(fn ($process) => str_contains(is_array($process->command) ? implode(' ', $process->command) : $process->command, 'chromium')
        ? Process::result(exitCode: 1) : Process::result(output: 'v26.0.0'));
    $this->post('/prospectos/google-maps/comprobar')->assertRedirect()->assertSessionHas('warning');
    $health = $engine->health();
    expect($health['checks']['node']['ready'])->toBeTrue()->and($health['checks']['chrome']['ready'])->toBeFalse()
        ->and($health['checks']['queue']['ready'])->toBeTrue()->and($health['message'])->toContain('Chrome/Chromium');
});

test('guardar configuracion incompleta avisa lo que falta y no anuncia que el motor esta listo', function () {
    Process::fake(['*' => Process::result(output: 'v20.0.0')]);
    config()->set('queue.default', 'sync');
    $this->patch('/prospectos/google-maps/configuracion', ['node_binary' => 'node', 'chrome_binary' => '/test/chromium', 'queue_connection' => 'database'])
        ->assertRedirect()->assertSessionHas('warning')->assertSessionMissing('success');
    $health = app(ProspectoScrapingService::class)->health();
    expect($health['queue_ready'])->toBeTrue()->and(config('queue.default'))->toBe('database')
        ->and($health['checks']['node']['ready'])->toBeFalse()->and($health['message'])->toContain('22');
});

test('no sobreescribe un prospecto manual que ya corresponde al mismo comercio y direccion', function () {
    $run = scrapingRun();
    (new ScrapeGoogleMapsProspectos($run->id))->handle(app(ProspectoScrapingService::class));
    $run->refresh();
    Prospecto::create(['owner_id' => 10, 'nombre' => 'Contacto existente', 'empresa' => 'Panadería Central', 'direccion' => 'Calle 123', 'estado' => 'contactado']);
    $counts = app(ProspectoScrapingService::class)->import($run, array_column($run->results, 'id'));
    expect($counts)->toBe(['created' => 0, 'skipped' => 1])->and(Prospecto::count())->toBe(1)
        ->and(Prospecto::firstOrFail()->estado)->toBe('contactado');
});

test('cancelar evita que un trabajo pendiente vuelva a iniciar', function () {
    $run = scrapingRun();
    $this->post("/prospectos/google-maps/{$run->id}/cancelar")->assertRedirect();
    (new ScrapeGoogleMapsProspectos($run->id))->handle(app(ProspectoScrapingService::class));
    expect($run->fresh()->status)->toBe('cancelled');
    Process::assertNothingRan();
});

test('un fallo al encolar no deja una busqueda pendiente que bloquee nuevos intentos', function ($afterCommit) {
    Bus::shouldReceive('dispatch')->once()->andReturnUsing(function () use ($afterCommit): void {
        if ($afterCommit) {
            DB::afterCommit(fn () => throw new RuntimeException('Cola no disponible'));

            return;
        }
        throw new RuntimeException('Cola no disponible');
    });
    $this->post('/prospectos/google-maps/buscar', ['query' => 'Panadería', 'location' => 'Chile', 'max_results' => 2])->assertSessionHasErrors('query');
    expect(ProspectoScrapeRun::whereIn('status', ['queued', 'running'])->count())->toBe(0);
})->with([false, true]);

test('un bloqueo o fallo del navegador queda visible y no crea prospectos', function ($status) {
    Process::fake(['*' => Process::result(output: json_encode(['status' => $status, 'results' => []]))]);
    $run = scrapingRun();
    (new ScrapeGoogleMapsProspectos($run->id))->handle(app(ProspectoScrapingService::class));
    expect($run->fresh()->status)->toBe($status)->and($run->fresh()->error)->not->toBeNull()->and(Prospecto::count())->toBe(0);
})->with(['blocked', 'failed']);

test('elimina enlaces peligrosos y no acepta seleccionar negocios fuera del resultado', function () {
    $payload = scrapingEnginePayload();
    $payload['results'][0]['website'] = 'javascript:alert(1)';
    Process::fake(['*' => Process::result(output: json_encode($payload))]);
    $run = scrapingRun();
    (new ScrapeGoogleMapsProspectos($run->id))->handle(app(ProspectoScrapingService::class));
    expect($run->fresh()->results[0]['website'])->toBeNull();
    $this->post("/prospectos/google-maps/{$run->id}/importar", ['ids' => [str_repeat('f', 64)]])->assertSessionHasErrors('ids');
    expect(Prospecto::count())->toBe(0);
});
