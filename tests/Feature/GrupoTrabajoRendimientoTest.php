<?php

use App\Models\Conductor;
use App\Models\Entrega;
use App\Models\GrupoTrabajo;
use App\Models\GrupoTrabajoAsignacion;
use App\Models\User;
use App\Models\Venta;
use Spatie\Permission\Models\Permission;

function giveGrupoTrabajoPermissions(User $user, array $actions = ['viewAny', 'view', 'create', 'edit', 'delete']): void
{
    $permissions = array_map(fn ($a) => Permission::firstOrCreate(['name' => "flota.grupos-trabajo.{$a}"]), $actions);
    $user->givePermissionTo($permissions);
}

function createFreshUser(): User
{
    $user = User::factory()->create();
    $user->syncRoles([]);

    return $user;
}

beforeEach(function () {
    $this->user = createFreshUser();
    giveGrupoTrabajoPermissions($this->user);
    $this->actingAs($this->user);
});

test('rendimiento page requiere autenticacion', function () {
    auth()->logout();

    $this->get(route('grupos-trabajo.rendimiento.index'))->assertRedirect(route('login'));
});

test('rendimiento page requiere permiso viewAny', function () {
    $user = createFreshUser();

    $this->actingAs($user)
        ->get(route('grupos-trabajo.rendimiento.index'))
        ->assertForbidden();
});

test('rendimiento page carga correctamente sin grupos', function () {
    $this->get(route('grupos-trabajo.rendimiento.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Backend/GruposTrabajo/Rendimiento')
            ->has('grupos', 0)
            ->has('asignacionesActivas', 0)
            ->has('rendimiento')
            ->has('tendencia')
            ->has('comparativa')
        );
});

test('rendimiento page carga con grupos activos', function () {
    GrupoTrabajo::factory()
        ->count(2)
        ->create(['owner_id' => $this->user->id, 'estado' => 'activo']);

    $this->get(route('grupos-trabajo.rendimiento.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Backend/GruposTrabajo/Rendimiento')
            ->has('grupos', 2)
        );
});

test('crear asignacion requiere permiso edit', function () {
    $user = createFreshUser();
    giveGrupoTrabajoPermissions($user, ['viewAny']);

    $grupo = GrupoTrabajo::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('grupos-trabajo.rendimiento.store'), [
            'grupo_trabajo_id' => $grupo->id,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
            'meta_monto' => 1000000,
        ])
        ->assertForbidden();
});

test('crear asignacion exitosamente', function () {
    $grupo = GrupoTrabajo::factory()->create(['owner_id' => $this->user->id]);

    $this->post(route('grupos-trabajo.rendimiento.store'), [
        'grupo_trabajo_id' => $grupo->id,
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-01-31',
        'meta_monto' => 1500000,
        'meta_cantidad' => 10,
        'meta_kg' => 500,
        'meta_l' => 200,
        'notas' => 'Meta mensual',
    ])->assertRedirect(route('grupos-trabajo.rendimiento.index'));

    $this->assertDatabaseHas('grupo_trabajo_asignaciones', [
        'grupo_trabajo_id' => $grupo->id,
        'meta_monto' => 1500000,
        'meta_cantidad' => 10,
        'estado' => 'activa',
    ]);
});

test('asignacion valida fechas', function () {
    $grupo = GrupoTrabajo::factory()->create(['owner_id' => $this->user->id]);

    $this->post(route('grupos-trabajo.rendimiento.store'), [
        'grupo_trabajo_id' => $grupo->id,
        'fecha_inicio' => '2026-01-31',
        'fecha_fin' => '2026-01-01',
    ])->assertSessionHasErrors('fecha_fin');
});

test('actualizar asignacion', function () {
    $grupo = GrupoTrabajo::factory()->create(['owner_id' => $this->user->id]);
    $asignacion = GrupoTrabajoAsignacion::factory()->create([
        'owner_id' => $this->user->id,
        'grupo_trabajo_id' => $grupo->id,
        'user_id' => $this->user->id,
    ]);

    $this->put(route('grupos-trabajo.rendimiento.update', $asignacion), [
        'fecha_inicio' => '2026-02-01',
        'fecha_fin' => '2026-02-28',
        'meta_monto' => 2000000,
        'estado' => 'completada',
    ])->assertRedirect(route('grupos-trabajo.rendimiento.index'));

    $this->assertDatabaseHas('grupo_trabajo_asignaciones', [
        'id' => $asignacion->id,
        'meta_monto' => 2000000,
        'estado' => 'completada',
    ]);
});

test('eliminar asignacion', function () {
    $grupo = GrupoTrabajo::factory()->create(['owner_id' => $this->user->id]);
    $asignacion = GrupoTrabajoAsignacion::factory()->create([
        'owner_id' => $this->user->id,
        'grupo_trabajo_id' => $grupo->id,
        'user_id' => $this->user->id,
    ]);

    $this->delete(route('grupos-trabajo.rendimiento.destroy', $asignacion))
        ->assertRedirect(route('grupos-trabajo.rendimiento.index'));

    $this->assertSoftDeleted('grupo_trabajo_asignaciones', ['id' => $asignacion->id]);
});

test('rendimiento page muestra asignaciones activas', function () {
    $grupo = GrupoTrabajo::factory()->create(['owner_id' => $this->user->id, 'estado' => 'activo']);
    GrupoTrabajoAsignacion::factory()->activa()->create([
        'owner_id' => $this->user->id,
        'grupo_trabajo_id' => $grupo->id,
        'user_id' => $this->user->id,
    ]);

    $this->get(route('grupos-trabajo.rendimiento.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Backend/GruposTrabajo/Rendimiento')
            ->has('asignacionesActivas', 1)
        );
});

test('rendimiento page notifica corte proximo', function () {
    $grupo = GrupoTrabajo::factory()->create(['owner_id' => $this->user->id, 'estado' => 'activo']);
    $manana = now()->addDay()->toDateString();

    GrupoTrabajoAsignacion::factory()->activa()->create([
        'owner_id' => $this->user->id,
        'grupo_trabajo_id' => $grupo->id,
        'user_id' => $this->user->id,
        'fecha_fin' => $manana,
    ]);

    $this->get(route('grupos-trabajo.rendimiento.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('diasParaCorte', fn ($v) => $v > 0 && $v <= 2)
        );
});

test('rendimiento page calcula metricas', function () {
    $grupo = GrupoTrabajo::factory()->create(['owner_id' => $this->user->id, 'estado' => 'activo']);
    $conductor = Conductor::factory()->create(['owner_id' => $this->user->id]);
    $grupo->conductores()->attach($conductor->id);

    $venta = Venta::factory()->create([
        'owner_id' => $this->user->id,
        'estado' => 'pagada',
        'total' => 500000,
        'fecha' => now()->format('Y-m-d'),
    ]);

    Entrega::factory()->create([
        'owner_id' => $this->user->id,
        'venta_id' => $venta->id,
        'conductor_id' => $conductor->id,
    ]);

    $this->get(route('grupos-trabajo.rendimiento.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('rendimiento')
        );
});

test('grupo inactivo no aparece en rendimiento', function () {
    GrupoTrabajo::factory()->create([
        'owner_id' => $this->user->id,
        'estado' => 'inactivo',
    ]);

    $this->get(route('grupos-trabajo.rendimiento.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('grupos', 0)
        );
});
