<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Purga física e incondicional de una cuenta y de todos sus datos vinculados.
 *
 * La operación se ejecuta en una única transacción. Las filas se eliminan
 * siguiendo el orden inverso de dependencia de las claves foráneas y, además,
 * se desactivan temporalmente las comprobaciones del motor para cubrir tablas
 * con restricciones RESTRICT o ciclos de referencia.
 */
class UserPurgeService
{
    /**
     * Tablas del framework que no contienen datos de negocio del usuario.
     *
     * @var list<string>
     */
    private const SYSTEM_TABLES = [
        'migrations',
        'cache',
        'cache_locks',
        'job_batches',
        'failed_jobs',
        'sqlite_sequence',
    ];

    /**
     * Columnas que, por convención de la aplicación, apuntan directamente al
     * usuario aunque algunas tablas no declaren la clave foránea.
     *
     * @var list<string>
     */
    private const DIRECT_USER_COLUMNS = [
        'user_id',
        'usuario_id',
        'sender_id',
        'receiver_id',
        'vendedor_id',
        'followed_id',
    ];

    /**
     * Columnas que se conservan como trazas de auditoría, pero sin apuntar al
     * usuario eliminado.
     *
     * @var list<string>
     */
    private const NULLABLE_AUDIT_COLUMNS = [
        'created_by',
    ];

    /**
     * Columnas de creación/atribución que no deben eliminar registros ajenos.
     *
     * @var list<string>
     */
    private const NULLABLE_CREATOR_COLUMNS = [
        'creator_id',
    ];

    /**
     * Columnas sin clave foránea declarada que pertenecen al usuario.
     *
     * @var array<string, list<string>>
     */
    private const EXTRA_USER_COLUMNS = [
        'raffles' => ['user_id'],
        'raffle_participants' => ['user_id'],
    ];

    /**
     * Caché de claves foráneas por tabla durante la purga.
     *
     * @var array<string, list<array{columns: list<string>, foreign_table: string}>>
     */
    private array $foreignKeysCache = [];

    /**
     * Caché de claves foráneas completas por tabla durante la purga.
     *
     * @var array<string, list<array{columns: list<string>, foreign_table: string}>>
     */
    private array $rawForeignKeysCache = [];

    /**
     * Caché del tipo morph del usuario.
     *
     * @var list<string>|null
     */
    private ?array $morphTypes = null;

    /**
     * Elimina permanentemente al usuario y todo su rastro.
     *
     * @return array{deleted_users: int, deleted_records: int, deleted_files: int}
     */
    public function purge(User $user): array
    {
        $tenantWide = $this->isTenantWidePurge($user);
        $userIds = $this->resolveUserIds($user, $tenantWide);
        $emails = $this->emailsFor($userIds);

        $plan = $this->buildPlan($tenantWide);
        $filePaths = $this->collectFilePaths($plan, $userIds);

        $deletedRecords = DB::transaction(function () use ($plan, $userIds, $emails): int {
            $deleted = 0;

            $this->withoutForeignKeyChecks(function () use ($plan, $userIds, $emails, &$deleted): void {
                foreach ($this->orderedTables(array_keys($plan)) as $table) {
                    $deleted += $this->purgeTable($table, $plan[$table], $userIds);
                }

                $deleted += $this->deletePasswordResetTokens($emails);

                $this->detachRemainingUserReferences($userIds);
                $this->detachSelfReferences($userIds);

                $deleted += DB::table('users')->whereIn('id', $userIds)->delete();
            });

            return $deleted;
        });

        return [
            'deleted_users' => count($userIds),
            'deleted_records' => $deletedRecords,
            'deleted_files' => $this->deleteFiles($filePaths),
        ];
    }

    /**
     * Solo los propietarios reales de un negocio arrastran a todo su tenant.
     * Los roles de plataforma (Master / Super Admin) nunca deben provocar la
     * eliminación masiva de otras cuentas.
     */
    private function isTenantWidePurge(User $user): bool
    {
        return $user->isTenantOwner()
            && ! $user->hasAnyRole(['Master', 'Super Admin']);
    }

    /**
     * @return list<int>
     */
    private function resolveUserIds(User $user, bool $tenantWide): array
    {
        $userIds = [$user->id];

        if (! $tenantWide) {
            return $userIds;
        }

        do {
            $descendants = DB::table('users')
                ->whereNotIn('id', $userIds)
                ->where(function (Builder $query) use ($user, $userIds): void {
                    $query->where('owner_id', $user->id)
                        ->orWhereIn('creator_id', $userIds);
                })
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $userIds = array_values(array_unique(array_merge($userIds, $descendants)));
        } while ($descendants !== []);

        return $userIds;
    }

    /**
     * @param  list<int>  $userIds
     * @return list<string>
     */
    private function emailsFor(array $userIds): array
    {
        return DB::table('users')
            ->whereIn('id', $userIds)
            ->pluck('email')
            ->all();
    }

    /**
     * Construye el mapa de tablas/columnas que deben purgarse.
     *
     * @return array<string, array{
     *     delete: list<string>,
     *     null: list<string>,
     *     morph: list<array{id: string, type: string}>,
     *     files: list<string>
     * }>
     */
    private function buildPlan(bool $tenantWide): array
    {
        $plan = [];

        foreach (Schema::getTableListing(null, false) as $table) {
            if ($table === 'users' || in_array($table, self::SYSTEM_TABLES, true)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);

            if ($columns === []) {
                continue;
            }

            $deleteColumns = array_values(array_intersect($columns, self::DIRECT_USER_COLUMNS));

            foreach ($this->foreignKeyColumnsReferencingUsers($table) as $column) {
                if (in_array($column, self::NULLABLE_AUDIT_COLUMNS, true)) {
                    continue;
                }

                if (in_array($column, self::NULLABLE_CREATOR_COLUMNS, true) && ! $tenantWide) {
                    continue;
                }

                $deleteColumns[] = $column;
            }

            // owner_id identifica al tenant dueño del registro. Si el usuario
            // eliminado es el propietario, toda la fila le pertenece.
            if (in_array('owner_id', $columns, true)) {
                $deleteColumns[] = 'owner_id';
            }

            if ($tenantWide && in_array('creator_id', $columns, true)) {
                $deleteColumns[] = 'creator_id';
            }

            $deleteColumns = array_values(array_unique(array_merge(
                $deleteColumns,
                array_intersect($columns, self::EXTRA_USER_COLUMNS[$table] ?? []),
            )));

            $nullColumns = array_values(array_intersect($columns, self::NULLABLE_AUDIT_COLUMNS));

            if (! $tenantWide && in_array('creator_id', $columns, true)) {
                $nullColumns[] = 'creator_id';
            }

            if (in_array('referred_by', $columns, true)) {
                $nullColumns[] = 'referred_by';
            }

            $nullColumns = array_values(array_unique($nullColumns));
            $morphColumns = $this->morphColumns($columns);
            $fileColumns = $this->fileColumns($columns);

            if ($deleteColumns === [] && $nullColumns === [] && $morphColumns === []) {
                continue;
            }

            $plan[$table] = [
                'delete' => $deleteColumns,
                'null' => $nullColumns,
                'morph' => $morphColumns,
                'files' => $fileColumns,
            ];
        }

        return $plan;
    }

    /**
     * @return list<string>
     */
    private function foreignKeyColumnsReferencingUsers(string $table): array
    {
        if (isset($this->foreignKeysCache[$table])) {
            return $this->foreignKeysCache[$table];
        }

        $columns = [];

        foreach ($this->rawForeignKeys($table) as $foreignKey) {
            $foreignTable = (string) ($foreignKey['foreign_table'] ?? '');

            if ($foreignTable !== 'users' && ! str_ends_with($foreignTable, '.users')) {
                continue;
            }

            foreach ((array) ($foreignKey['columns'] ?? []) as $column) {
                $columns[] = (string) $column;
            }
        }

        return $this->foreignKeysCache[$table] = array_values(array_unique($columns));
    }

    /**
     * @param  list<string>  $columns
     * @return list<array{id: string, type: string}>
     */
    private function morphColumns(array $columns): array
    {
        $morphColumns = [];

        foreach ($columns as $column) {
            if (! str_ends_with($column, '_type')) {
                continue;
            }

            $idColumn = Str::beforeLast($column, '_type').'_id';

            if (! in_array($idColumn, $columns, true)) {
                continue;
            }

            $morphColumns[] = [
                'id' => $idColumn,
                'type' => $column,
            ];
        }

        return $morphColumns;
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function fileColumns(array $columns): array
    {
        return array_values(array_filter($columns, function (string $column): bool {
            $name = Str::lower($column);

            if (in_array($name, ['archivo_nombre', 'archivo_tipo', 'file_name', 'file_type'], true)) {
                return false;
            }

            return str_ends_with($name, '_path')
                || in_array($name, ['archivo', 'foto', 'cover_image', 'image_url', 'app_logo', 'app_favicon', 'avatar', 'logo', 'favicon'], true)
                || str_starts_with($name, 'imagen')
                || str_starts_with($name, 'image_')
                || str_contains($name, 'photo')
                || str_contains($name, 'logo')
                || str_contains($name, 'cover')
                || str_contains($name, 'favicon')
                || str_contains($name, 'certificado');
        }));
    }

    /**
     * Ordena las tablas de hijos a padres para minimizar conflictos de FK.
     * Los ciclos se resuelven con la desactivación temporal de restricciones.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function orderedTables(array $tables): array
    {
        $tableSet = array_flip($tables);
        $children = array_fill_keys($tables, []);
        $inDegree = array_fill_keys($tables, 0);

        foreach ($tables as $table) {
            foreach ($this->rawForeignKeys($table) as $foreignKey) {
                $parent = (string) ($foreignKey['foreign_table'] ?? '');

                if ($parent === $table || ! isset($tableSet[$parent])) {
                    continue;
                }

                $children[$parent][] = $table;
                $inDegree[$table]++;
            }
        }

        $queue = array_keys(array_filter($inDegree, fn (int $degree): bool => $degree === 0));
        $ordered = [];

        while ($queue !== []) {
            $table = array_shift($queue);
            $ordered[] = $table;

            foreach ($children[$table] as $child) {
                $inDegree[$child]--;

                if ($inDegree[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }

        $ordered = array_reverse($ordered);

        foreach ($tables as $table) {
            if (! in_array($table, $ordered, true)) {
                $ordered[] = $table;
            }
        }

        return $ordered;
    }

    /**
     * @return list<array{columns: list<string>, foreign_table: string}>
     */
    private function rawForeignKeys(string $table): array
    {
        if (isset($this->rawForeignKeysCache[$table])) {
            return $this->rawForeignKeysCache[$table];
        }

        try {
            return $this->rawForeignKeysCache[$table] = Schema::getForeignKeys($table);
        } catch (Throwable $exception) {
            Log::warning('No se pudieron inspeccionar las claves foráneas de una tabla', [
                'table' => $table,
                'message' => $exception->getMessage(),
            ]);

            return $this->rawForeignKeysCache[$table] = [];
        }
    }

    /**
     * @param  array{delete: list<string>, null: list<string>, morph: list<array{id: string, type: string}>, files: list<string>}  $configuration
     * @param  list<int>  $userIds
     */
    private function purgeTable(string $table, array $configuration, array $userIds): int
    {
        $deleted = 0;

        if ($configuration['delete'] !== [] || $configuration['morph'] !== []) {
            $deleted += $this->scopedQuery($table, $configuration, $userIds)->delete();
        }

        if ($configuration['null'] !== []) {
            $update = array_fill_keys($configuration['null'], null);

            DB::table($table)
                ->where(function (Builder $query) use ($configuration, $userIds): void {
                    foreach ($configuration['null'] as $column) {
                        $query->orWhereIn($column, $userIds);
                    }
                })
                ->update($update);
        }

        return $deleted;
    }

    /**
     * @param  array{delete: list<string>, null: list<string>, morph: list<array{id: string, type: string}>, files: list<string>}  $configuration
     * @param  list<int>  $userIds
     */
    private function scopedQuery(string $table, array $configuration, array $userIds): Builder
    {
        return DB::table($table)->where(function (Builder $query) use ($configuration, $userIds): void {
            foreach ($configuration['delete'] as $column) {
                $query->orWhereIn($column, $userIds);
            }

            foreach ($configuration['morph'] as $morph) {
                $query->orWhere(function (Builder $morphQuery) use ($morph, $userIds): void {
                    $morphQuery
                        ->whereIn($morph['id'], $userIds)
                        ->whereIn($morph['type'], $this->morphTypes());
                });
            }
        });
    }

    /**
     * @return list<string>
     */
    private function morphTypes(): array
    {
        if ($this->morphTypes !== null) {
            return $this->morphTypes;
        }

        return $this->morphTypes = array_values(array_unique([
            User::class,
            (new User)->getMorphClass(),
            'user',
            'App\\Models\\User',
        ]));
    }

    /**
     * @param  array<string, array{delete: list<string>, null: list<string>, morph: list<array{id: string, type: string}>, files: list<string>}>  $plan
     * @param  list<int>  $userIds
     * @return list<string>
     */
    private function collectFilePaths(array $plan, array $userIds): array
    {
        $paths = $this->userFilePaths($userIds);

        foreach ($plan as $table => $configuration) {
            if ($configuration['files'] === []) {
                continue;
            }

            if ($configuration['delete'] === [] && $configuration['morph'] === []) {
                continue;
            }

            $rows = $this->scopedQuery($table, $configuration, $userIds)
                ->select($configuration['files'])
                ->get();

            foreach ($rows as $row) {
                foreach ($configuration['files'] as $column) {
                    $path = $this->normalizeStoredPath($row->{$column} ?? null);

                    if ($path !== null) {
                        $paths[] = $path;
                    }
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<int>  $userIds
     * @return list<string>
     */
    private function userFilePaths(array $userIds): array
    {
        $userColumns = Schema::getColumnListing('users');
        $columns = array_values(array_intersect($userColumns, $this->fileColumns($userColumns)));

        if ($columns === []) {
            return [];
        }

        $paths = [];

        foreach (DB::table('users')->whereIn('id', $userIds)->get($columns) as $row) {
            foreach ($columns as $column) {
                $path = $this->normalizeStoredPath($row->{$column} ?? null);

                if ($path !== null) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    private function normalizeStoredPath(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || str_starts_with($value, 'data:')) {
            return null;
        }

        if (preg_match('~^https?://~i', $value) === 1) {
            $appUrl = rtrim((string) config('app.url'), '/');

            if ($appUrl === '' || ! str_starts_with($value, $appUrl)) {
                return null;
            }

            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        $value = ltrim($value, '/');
        $value = preg_replace('~^storage/~', '', $value) ?? $value;
        $value = preg_replace('~^public/~', '', $value) ?? $value;

        if ($value === '' || str_contains($value, '..')) {
            return null;
        }

        return $value;
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteFiles(array $paths): int
    {
        if ($paths === []) {
            return 0;
        }

        $disks = ['public', 'local'];

        if ((string) config('filesystems.disks.s3.bucket') !== '') {
            $disks[] = 's3';
        }

        $deleted = 0;

        foreach ($paths as $path) {
            foreach ($disks as $disk) {
                try {
                    if (Storage::disk($disk)->exists($path)) {
                        Storage::disk($disk)->delete($path);
                        $deleted++;

                        break;
                    }
                } catch (Throwable $exception) {
                    Log::warning('No se pudo eliminar un archivo asociado al usuario', [
                        'disk' => $disk,
                        'path' => $path,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return $deleted;
    }

    /**
     * @param  list<int>  $userIds
     */
    private function detachRemainingUserReferences(array $userIds): void
    {
        foreach (['creator_id', 'owner_id', 'referred_by'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                continue;
            }

            DB::table('users')
                ->whereNotIn('id', $userIds)
                ->whereIn($column, $userIds)
                ->update([$column => null]);
        }
    }

    /**
     * Rompe las auto-referencias del árbol que se va a eliminar para que el
     * DELETE no dependa del orden interno de comprobación de claves foráneas.
     *
     * @param  list<int>  $userIds
     */
    private function detachSelfReferences(array $userIds): void
    {
        $updates = [];

        foreach (['creator_id', 'owner_id', 'referred_by'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $updates[$column] = null;
            }
        }

        if ($updates === []) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $userIds)
            ->update($updates);
    }

    /**
     * @param  list<string>  $emails
     */
    private function deletePasswordResetTokens(array $emails): int
    {
        if ($emails === [] || ! Schema::hasTable('password_reset_tokens')) {
            return 0;
        }

        return DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
    }

    private function withoutForeignKeyChecks(Closure $callback): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA defer_foreign_keys = ON');

            try {
                $callback();
            } finally {
                DB::statement('PRAGMA defer_foreign_keys = OFF');
            }

            return;
        }

        Schema::withoutForeignKeyConstraints($callback);
    }
}
