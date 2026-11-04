<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const RESOURCE_MAP = [
        'ventas.pos-cierre-caja' => 'ventas.cierre-caja',
        'ventas.pos-facturacion' => 'ventas.facturacion-pos',
    ];

    private const ACTIONS = ['viewAny', 'view', 'create', 'edit', 'delete', 'import', 'export'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = (string) config('auth.defaults.guard', 'web');

        foreach (self::RESOURCE_MAP as $sourceResource => $targetResource) {
            foreach (self::ACTIONS as $action) {
                $source = Permission::where('name', "{$sourceResource}.{$action}")
                    ->where('guard_name', $guardName)
                    ->first();
                $target = Permission::firstOrCreate([
                    'name' => "{$targetResource}.{$action}",
                    'guard_name' => $guardName,
                ]);

                if ($source) {
                    $this->copyAssignments($source, $target);
                    $source->delete();
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = (string) config('auth.defaults.guard', 'web');

        foreach (self::RESOURCE_MAP as $sourceResource => $targetResource) {
            foreach (self::ACTIONS as $action) {
                $source = Permission::firstOrCreate([
                    'name' => "{$sourceResource}.{$action}",
                    'guard_name' => $guardName,
                ]);
                $target = Permission::where('name', "{$targetResource}.{$action}")
                    ->where('guard_name', $guardName)
                    ->first();

                if ($target) {
                    $this->copyAssignments($target, $source);
                    $target->delete();
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function copyAssignments(Permission $source, Permission $target): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $permissionPivot = $columnNames['permission_pivot_key'] ?? 'permission_id';
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
