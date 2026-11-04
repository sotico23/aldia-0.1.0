<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ACTIONS = ['viewAny', 'view', 'create', 'edit', 'delete', 'import', 'export'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = (string) config('auth.defaults.guard', 'web');
        $permissionMap = [
            'ventas.pos' => [
                'ventas.pos-cierre-caja',
                'ventas.pos-facturacion',
                'ventas.pos-reportes',
            ],
            'comercial.oportunidades' => [
                'comercial.mensajes-marketplace',
            ],
            'rifas.rifas' => [
                'rifas.sorteos',
            ],
        ];

        foreach ($permissionMap as $sourceResource => $targetResources) {
            foreach (self::ACTIONS as $action) {
                $sourcePermission = Permission::where('name', "{$sourceResource}.{$action}")
                    ->where('guard_name', $guardName)
                    ->first();

                foreach ($targetResources as $targetResource) {
                    $targetPermission = Permission::firstOrCreate([
                        'name' => "{$targetResource}.{$action}",
                        'guard_name' => $guardName,
                    ]);

                    if ($sourcePermission) {
                        $this->copyAssignments($sourcePermission, $targetPermission);
                    }
                }
            }
        }

        $zonePermissionMap = [
            'delivery.zonas-reparto.viewAny' => ['zonas-reparto.view'],
            'delivery.zonas-reparto.view' => ['zonas-reparto.view'],
            'delivery.zonas-reparto.create' => ['zonas-reparto.create'],
            'delivery.zonas-reparto.edit' => ['zonas-reparto.edit'],
            'delivery.zonas-reparto.delete' => ['zonas-reparto.delete'],
            'delivery.zonas-reparto.import' => [],
            'delivery.zonas-reparto.export' => [],
        ];

        foreach ($zonePermissionMap as $targetName => $sourceNames) {
            $targetPermission = Permission::firstOrCreate([
                'name' => $targetName,
                'guard_name' => $guardName,
            ]);

            foreach ($sourceNames as $sourceName) {
                $sourcePermission = Permission::where('name', $sourceName)
                    ->where('guard_name', $guardName)
                    ->first();

                if ($sourcePermission) {
                    $this->copyAssignments($sourcePermission, $targetPermission);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep generated permissions and assignments: administrators may have edited them after migration.
    }

    private function copyAssignments(Permission $source, Permission $target): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $permissionPivot = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $rolePivot = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelMorph = $columnNames['model_morph_key'] ?? 'model_id';

        foreach (DB::table($tableNames['role_has_permissions'])
            ->where($permissionPivot, $source->getKey())
            ->get() as $assignment) {
            $row = (array) $assignment;
            $row[$permissionPivot] = $target->getKey();

            DB::table($tableNames['role_has_permissions'])->insertOrIgnore($row);
        }

        foreach (DB::table($tableNames['model_has_permissions'])
            ->where($permissionPivot, $source->getKey())
            ->get() as $assignment) {
            $row = (array) $assignment;
            $row[$permissionPivot] = $target->getKey();
            $row[$modelMorph] = $assignment->{$modelMorph};

            DB::table($tableNames['model_has_permissions'])->insertOrIgnore($row);
        }
    }
};
