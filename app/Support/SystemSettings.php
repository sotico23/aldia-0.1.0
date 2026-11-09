<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSettings
{
    public const DB_OVERRIDE_KEY = 'SYSTEM_SETTINGS_DB_OVERRIDE';

    private const SNAPSHOT_VERSION = 1;

    /**
     * Flattened field catalog keyed by environment variable name.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::groups() as $groupId => $group) {
            foreach (($group['fields'] ?? []) as $key => $field) {
                $fields[$key] = [...$field, 'group' => $groupId];
            }
        }

        return $fields;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function groups(): array
    {
        return config('system_settings.groups', []);
    }

    /**
     * Return every persisted setting, preferring the encrypted snapshot so
     * database credentials are available before the database is resolved.
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        $snapshot = self::readSnapshot();

        return $snapshot ?? self::loadFromDatabase();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = self::values();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public static function has(string $key): bool
    {
        return filled(self::get($key));
    }

    /**
     * Persist a batch of settings.
     *
     * @param  array<string, array{value?: string|null, secret?: bool, group?: string}>  $entries
     */
    public static function save(array $entries): void
    {
        DB::transaction(function () use ($entries): void {
            foreach ($entries as $key => $entry) {
                $setting = Setting::query()->firstOrNew(['key' => $key]);

                $setting->group = $entry['group'] ?? $setting->group ?? 'general';
                $setting->is_encrypted = (bool) ($entry['secret'] ?? false);

                if (array_key_exists('value', $entry)) {
                    $setting->value = $entry['value'];
                }

                $setting->save();
            }
        });

        self::refreshSnapshot();
    }

    public static function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();

        self::refreshSnapshot();
    }

    /**
     * Read settings directly from the database, decrypting sensitive rows.
     *
     * @return array<string, string|null>
     */
    public static function loadFromDatabase(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::query()
                ->get()
                ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Rebuild the encrypted snapshot from the current database state.
     */
    public static function refreshSnapshot(): void
    {
        if (self::snapshotsDisabled()) {
            return;
        }

        try {
            self::writeSnapshot(self::loadFromDatabase());
        } catch (Throwable $exception) {
            Log::warning('No se pudo escribir el snapshot de configuración del sistema.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Apply the stored values to Laravel's runtime configuration.
     */
    public static function applyToConfig(): void
    {
        $values = self::values();

        if ($values === []) {
            return;
        }

        foreach (self::fields() as $key => $field) {
            if (! array_key_exists($key, $values) || blank($values[$key])) {
                continue;
            }

            foreach ((array) ($field['config'] ?? []) as $target) {
                $value = $values[$key];

                if (($field['cast'] ?? null) === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif (($field['cast'] ?? null) === 'csv') {
                    $value = array_values(array_filter(
                        array_map('trim', explode(',', (string) $value)),
                        fn (string $item): bool => $item !== '',
                    ));
                } elseif (($field['type'] ?? null) === 'number') {
                    $value = (int) $value;
                }

                config([$target => $value]);
            }
        }

        if (isset($values['REVERB_SCHEME'])) {
            config([
                'broadcasting.connections.reverb.options.useTLS' => $values['REVERB_SCHEME'] === 'https',
                'reverb.apps.apps.0.options.useTLS' => $values['REVERB_SCHEME'] === 'https',
            ]);
        }

        if (($values[self::DB_OVERRIDE_KEY] ?? '0') === '1') {
            self::applyDatabaseConfig($values);
        }
    }

    /**
     * Whether the .env/config value is currently populated for a field.
     */
    public static function envHasValue(string $key): bool
    {
        $field = self::fields()[$key] ?? [];

        foreach ((array) ($field['config'] ?? []) as $target) {
            if (filled(config($target))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private static function applyDatabaseConfig(array $values): void
    {
        $connection = $values['DB_CONNECTION'] ?? null;

        if (! is_string($connection) || $connection === '') {
            return;
        }

        if ($connection === 'sqlite') {
            config(['database.default' => 'sqlite']);

            if (filled($values['DB_DATABASE'] ?? null)) {
                config(['database.connections.sqlite.database' => $values['DB_DATABASE']]);
            }

            return;
        }

        // Never break the boot process with a partial configuration.
        foreach (['DB_HOST', 'DB_DATABASE'] as $required) {
            if (blank($values[$required] ?? null)) {
                return;
            }
        }

        config(['database.default' => $connection]);

        $targets = in_array($connection, ['mysql', 'mariadb'], true)
            ? ['mysql', 'mariadb']
            : [$connection];

        $map = [
            'DB_HOST' => 'host',
            'DB_PORT' => 'port',
            'DB_DATABASE' => 'database',
            'DB_USERNAME' => 'username',
            'DB_PASSWORD' => 'password',
        ];

        foreach ($targets as $target) {
            foreach ($map as $key => $attribute) {
                if (array_key_exists($key, $values) && $values[$key] !== null) {
                    config(["database.connections.{$target}.{$attribute}" => $values[$key]]);
                }
            }
        }
    }

    /**
     * @return array<string, string|null>|null
     */
    public static function readSnapshot(): ?array
    {
        if (self::snapshotsDisabled()) {
            return null;
        }

        $path = self::snapshotPath();

        if (! is_file($path)) {
            return null;
        }

        try {
            $payload = json_decode(
                Crypt::decryptString((string) file_get_contents($path)),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (($payload['version'] ?? null) !== self::SNAPSHOT_VERSION) {
                return null;
            }

            return is_array($payload['values'] ?? null) ? $payload['values'] : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function forgetSnapshot(): void
    {
        $path = self::snapshotPath();

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private static function writeSnapshot(array $values): void
    {
        $path = self::snapshotPath();

        File::ensureDirectoryExists(dirname($path));

        $payload = Crypt::encryptString(json_encode([
            'version' => self::SNAPSHOT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'values' => $values,
        ], JSON_THROW_ON_ERROR));

        File::put($path, $payload, true);
    }

    private static function snapshotPath(): string
    {
        $configured = config('system_settings.snapshot_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return storage_path('framework/system-settings-'.app()->environment().'.json');
    }

    /**
     * Runtime injection and snapshots must never run while testing, otherwise
     * a snapshot written by a test could hijack the database connection.
     */
    public static function isRuntimeEnabled(): bool
    {
        return ! app()->runningUnitTests()
            && ! self::runningTestCommand()
            && (bool) config('system_settings.snapshot_enabled', true);
    }

    private static function runningTestCommand(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $command = implode(' ', array_slice($_SERVER['argv'] ?? [], 0, 3));

        return str_contains($command, 'pest')
            || str_contains($command, 'phpunit')
            || preg_match('/\btest\b/', $command) === 1;
    }

    private static function snapshotsDisabled(): bool
    {
        return ! self::isRuntimeEnabled();
    }
}
