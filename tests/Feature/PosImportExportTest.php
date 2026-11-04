<?php

use App\Models\PosCierreCaja;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->syncRoles([]);
    $this->user->syncPermissions([]);
});

test('facturacion metadata import updates only existing POS sales belonging to the owner', function () {
    $sale = Venta::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'es_pos' => true,
        'folio' => null,
        'tipo_dte' => null,
    ]);
    $this->user->givePermissionTo(Permission::firstOrCreate([
        'name' => 'ventas.facturacion-pos.import',
        'guard_name' => 'web',
    ]));
    $file = UploadedFile::fake()->createWithContent(
        'metadatos_dte.csv',
        "venta_id,folio,tipo_dte\n{$sale->id},123,33\n",
    );

    $this->actingAs($this->user)
        ->post(route('pos.facturacion.importar'), ['file' => $file])
        ->assertRedirect();

    expect($sale->fresh()->folio)->toBe('123')
        ->and($sale->fresh()->tipo_dte)->toBe('33');
});

test('facturacion metadata import rejects sales belonging to another tenant', function () {
    $otherOwner = User::factory()->create();
    $otherSale = Venta::factory()->create([
        'owner_id' => $otherOwner->getOwnerId(),
        'user_id' => $otherOwner->id,
        'es_pos' => true,
        'folio' => null,
        'tipo_dte' => null,
    ]);
    $this->user->givePermissionTo(Permission::firstOrCreate([
        'name' => 'ventas.facturacion-pos.import',
        'guard_name' => 'web',
    ]));
    $file = UploadedFile::fake()->createWithContent(
        'metadatos_dte.csv',
        "venta_id,folio,tipo_dte\n{$otherSale->id},123,33\n",
    );

    $this->actingAs($this->user)
        ->post(route('pos.facturacion.importar'), ['file' => $file])
        ->assertSessionHasErrors();

    expect($otherSale->fresh()->folio)->toBeNull()
        ->and($otherSale->fresh()->tipo_dte)->toBeNull();
});

test('facturacion export returns only the current tenant POS sales', function () {
    $sale = Venta::factory()->create([
        'owner_id' => $this->user->getOwnerId(),
        'user_id' => $this->user->id,
        'es_pos' => true,
        'numero' => 'POS-LOCAL',
    ]);
    $otherOwner = User::factory()->create();
    Venta::factory()->create([
        'owner_id' => $otherOwner->getOwnerId(),
        'user_id' => $otherOwner->id,
        'es_pos' => true,
        'numero' => 'POS-OTRA-EMPRESA',
    ]);
    $this->user->givePermissionTo(Permission::firstOrCreate([
        'name' => 'ventas.facturacion-pos.export',
        'guard_name' => 'web',
    ]));

    $this->actingAs($this->user)
        ->get(route('pos.facturacion.exportar', ['format' => 'json']))
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $sale->id, 'numero' => 'POS-LOCAL'])
        ->assertJsonMissing(['numero' => 'POS-OTRA-EMPRESA']);
});

test('cierre caja import stores historical closures under the current tenant', function () {
    $this->user->givePermissionTo(Permission::firstOrCreate([
        'name' => 'ventas.cierre-caja.import',
        'guard_name' => 'web',
    ]));
    $file = UploadedFile::fake()->createWithContent(
        'cierres.csv',
        "fecha_desde,fecha_hasta,total_efectivo,total_tarjeta,total_transferencia,total_otros,total_general,cantidad_ventas,referencia\n".
        '2026-10-01,2026-10-01,1000,200,300,50,1550,4,CIERRE-2026-10-01',
    );

    $this->actingAs($this->user)
        ->post(route('pos.cierre.importar'), ['file' => $file])
        ->assertRedirect();

    $this->assertDatabaseHas('pos_cierre_cajas', [
        'owner_id' => $this->user->getOwnerId(),
        'imported_by' => $this->user->id,
        'referencia' => 'CIERRE-2026-10-01',
        'total_general' => 1550,
    ]);
});

test('cierre caja export excludes historical closures belonging to another tenant', function () {
    $otherOwner = User::factory()->create();
    PosCierreCaja::create([
        'owner_id' => $this->user->getOwnerId(),
        'imported_by' => $this->user->id,
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-01',
        'total_general' => 10,
        'referencia' => 'CIERRE-LOCAL',
    ]);
    PosCierreCaja::create([
        'owner_id' => $otherOwner->getOwnerId(),
        'imported_by' => $otherOwner->id,
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-01',
        'total_general' => 20,
        'referencia' => 'CIERRE-EXTERNO',
    ]);
    $this->user->givePermissionTo(Permission::firstOrCreate([
        'name' => 'ventas.cierre-caja.export',
        'guard_name' => 'web',
    ]));

    $response = $this->actingAs($this->user)
        ->get(route('pos.cierre.exportar', ['format' => 'csv']));

    $response->assertSuccessful();

    expect($response->streamedContent())->toContain('CIERRE-LOCAL');
    expect($response->streamedContent())->not->toContain('CIERRE-EXTERNO');
});

test('POS import and export routes require their granular permissions', function () {
    $this->actingAs($this->user)
        ->get(route('pos.facturacion.exportar'))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('pos.cierre.exportar'))
        ->assertForbidden();
});
