<?php

use App\Helpers\PermissionHelper;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
});

it('defines the complete standard action set for granular sidebar resources', function () {
    $resources = [
        'comercial.mensajes-marketplace',
        'delivery.zonas-reparto',
        'ventas.cierre-caja',
        'ventas.facturacion-pos',
        'ventas.pos-reportes',
        'rifas.sorteos',
    ];
    $actions = ['viewAny', 'view', 'create', 'edit', 'delete', 'import', 'export'];

    foreach ($resources as $resource) {
        foreach ($actions as $action) {
            expect(Permission::where('name', "{$resource}.{$action}")->exists())
                ->toBeTrue("Missing permission {$resource}.{$action}");
        }
    }
});

it('groups granular resources under the same sidebar hierarchy in the role editor', function () {
    $groupedPermissions = PermissionHelper::getGroupedPermissionsByModuleSubgroupResource();

    expect(array_keys($groupedPermissions['TIENDA']))
        ->toContain('Terminal POS', 'Cierre de Caja', 'Facturación POS', 'Reportes Ventas', 'Mensajes Marketplace', 'Variantes / SKUs')
        ->and(array_keys($groupedPermissions['LOGÍSTICA']))
        ->toContain('Zonas de Reparto')
        ->and(array_keys($groupedPermissions['MARKETING']))
        ->toContain('Rifas y Sorteos');

    expect(array_keys($groupedPermissions['TIENDA']['Cierre de Caja']))
        ->toContain('Cierre de Caja')
        ->and(array_keys($groupedPermissions['TIENDA']['Mensajes Marketplace']))
        ->toContain('Mensajes Marketplace')
        ->and(array_keys($groupedPermissions['LOGÍSTICA']['Zonas de Reparto']))
        ->toContain('Zonas de Reparto');
});

it('preserves legacy zone permissions when creating granular permissions', function () {
    $role = Role::create([
        'name' => 'Legacy Zone Role',
        'guard_name' => 'web',
    ]);
    $legacyPermission = Permission::firstOrCreate([
        'name' => 'zonas-reparto.view',
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo($legacyPermission);

    $migration = require database_path('migrations/2026_11_03_205716_add_granular_sidebar_permissions.php');
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($role->fresh()->hasPermissionTo('delivery.zonas-reparto.viewAny'))
        ->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('delivery.zonas-reparto.view'))
        ->toBeTrue();
});

it('moves legacy POS resource assignments to the requested permission slugs', function () {
    $role = Role::create([
        'name' => 'Legacy POS Role',
        'guard_name' => 'web',
    ]);
    $legacyPermission = Permission::firstOrCreate([
        'name' => 'ventas.pos-cierre-caja.import',
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo($legacyPermission);

    $migration = require database_path('migrations/2026_11_03_212129_rename_pos_sidebar_permissions_to_requested_slugs.php');
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($role->fresh()->hasPermissionTo('ventas.cierre-caja.import'))
        ->toBeTrue()
        ->and(Permission::where('name', 'ventas.pos-cierre-caja.import')->exists())
        ->toBeFalse();
});

it('uses granular permissions on sidebar feature routes', function () {
    $routes = app('router')->getRoutes();

    expect($routes->getByName('pos.cierre.cerrar')->gatherMiddleware())
        ->toContain('permission:ventas.cierre-caja.edit')
        ->and($routes->getByName('pos.facturacion.importar')->gatherMiddleware())
        ->toContain('permission:ventas.facturacion-pos.import')
        ->and($routes->getByName('pos.cierre.importar')->gatherMiddleware())
        ->toContain('permission:ventas.cierre-caja.import')
        ->and($routes->getByName('pos.reportes.exportar')->gatherMiddleware())
        ->toContain('permission:ventas.pos-reportes.export')
        ->and($routes->getByName('zonas-reparto.store')->gatherMiddleware())
        ->toContain('permission:delivery.zonas-reparto.create')
        ->and($routes->getByName('pos.skus.store')->gatherMiddleware())
        ->toContain('permission:ventas.variantes.create')
        ->and($routes->getByName('raffles.prizes.destroy')->gatherMiddleware())
        ->toContain('permission:rifas.rifas.delete')
        ->and($routes->getByName('raffles.draw')->gatherMiddleware())
        ->toContain('permission:rifas.sorteos.create')
        ->and($routes->getByName('chat.send')->gatherMiddleware())
        ->toContain('permission:comercial.mensajes-marketplace.create');
});
