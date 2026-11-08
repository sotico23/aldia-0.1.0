<?php

namespace App\Livewire;

use App\Support\SystemSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use PDO;
use Throwable;

#[Layout('layouts.system-settings')]
#[Title('Ajustes del Servidor y Base de Datos')]
class SystemSettingsPage extends Component
{
    /**
     * Non-sensitive values, keyed by environment variable name.
     *
     * @var array<string, string|null>
     */
    public array $values = [];

    /**
     * New secret values. Existing secrets are never sent to the browser.
     *
     * @var array<string, string>
     */
    public array $secrets = [];

    /**
     * Whether a secret is already stored in the database.
     *
     * @var array<string, bool>
     */
    public array $hasStoredSecret = [];

    public bool $databaseOverrideEnabled = false;

    public function mount(): void
    {
        $stored = SystemSettings::values();

        foreach (SystemSettings::fields() as $key => $field) {
            if ($this->isSecret($field)) {
                $this->secrets[$key] = '';
                $this->hasStoredSecret[$key] = filled($stored[$key] ?? null);

                continue;
            }

            $this->values[$key] = array_key_exists($key, $stored)
                ? (string) $stored[$key]
                : (string) ($field['default'] ?? '');
        }

        $this->databaseOverrideEnabled = ($stored[SystemSettings::DB_OVERRIDE_KEY] ?? '0') === '1';
    }

    public function save(): void
    {
        $fields = SystemSettings::fields();

        $this->validate($this->rules($fields));

        $entries = $this->buildEntries($fields);

        if ($this->databaseOverrideEnabled && ! $this->testDatabaseConnection($entries)) {
            return;
        }

        SystemSettings::save($entries);
        SystemSettings::applyToConfig();

        foreach ($fields as $key => $field) {
            if ($this->isSecret($field)) {
                $this->secrets[$key] = '';
            }
        }

        $stored = SystemSettings::values();

        foreach ($fields as $key => $field) {
            if ($this->isSecret($field)) {
                $this->hasStoredSecret[$key] = filled($stored[$key] ?? null);
            }
        }

        session()->flash('system-settings-status', 'Configuración guardada correctamente.');
    }

    public function clearSecret(string $key): void
    {
        $field = SystemSettings::fields()[$key] ?? null;

        if ($field === null || ! $this->isSecret($field)) {
            return;
        }

        SystemSettings::forget($key);

        $this->secrets[$key] = '';
        $this->hasStoredSecret[$key] = false;

        session()->flash(
            'system-settings-status',
            'Valor eliminado. Se usará el valor del archivo .env si existe.',
        );
    }

    public function render()
    {
        $fields = SystemSettings::fields();

        return view('livewire.system-settings-page', [
            'groups' => SystemSettings::groups(),
            'fields' => $fields,
            'envConfigured' => collect($fields)
                ->mapWithKeys(fn (array $field, string $key): array => [
                    $key => SystemSettings::envHasValue($key),
                ])
                ->all(),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, array{value: string|null, secret: bool, group: string}>
     */
    private function buildEntries(array $fields): array
    {
        $entries = [];

        foreach ($fields as $key => $field) {
            if ($this->isSecret($field)) {
                $value = trim((string) ($this->secrets[$key] ?? ''));

                if ($value === '') {
                    continue;
                }

                $entries[$key] = [
                    'value' => $value,
                    'secret' => true,
                    'group' => $field['group'],
                ];

                continue;
            }

            $value = $this->values[$key] ?? null;

            $entries[$key] = [
                'value' => $value === '' ? null : $value,
                'secret' => false,
                'group' => $field['group'],
            ];
        }

        $entries[SystemSettings::DB_OVERRIDE_KEY] = [
            'value' => $this->databaseOverrideEnabled ? '1' : '0',
            'secret' => false,
            'group' => 'database',
        ];

        return $entries;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function rules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $key => $field) {
            if ($this->isSecret($field)) {
                $rules["secrets.{$key}"] = ['nullable', 'string', 'max:4000'];

                continue;
            }

            $rules["values.{$key}"] = match ($field['type'] ?? 'text') {
                'number' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'email' => ['nullable', 'email', 'max:255'],
                'select' => ['nullable', 'string', Rule::in(array_keys($field['options'] ?? []))],
                default => ['nullable', 'string', 'max:2000'],
            };
        }

        if ($this->databaseOverrideEnabled) {
            $rules['values.DB_CONNECTION'] = ['required', 'string', Rule::in(array_keys($fields['DB_CONNECTION']['options'] ?? []))];
            $rules['values.DB_DATABASE'] = ['required', 'string', 'max:255'];

            if (($this->values['DB_CONNECTION'] ?? null) !== 'sqlite') {
                $rules['values.DB_HOST'] = ['required', 'string', 'max:255'];
                $rules['values.DB_USERNAME'] = ['required', 'string', 'max:255'];
            }
        }

        return $rules;
    }

    /**
     * Validate the credentials before persisting them. A wrong password would
     * otherwise lock the application out on the next request.
     *
     * @param  array<string, array{value: string|null, secret: bool, group: string}>  $entries
     */
    private function testDatabaseConnection(array $entries): bool
    {
        $driver = $entries['DB_CONNECTION']['value'] ?? null;

        if ($driver === null || $driver === 'sqlite') {
            return true;
        }

        $host = $entries['DB_HOST']['value'] ?? null;
        $port = $entries['DB_PORT']['value'] ?? null;
        $database = $entries['DB_DATABASE']['value'] ?? null;
        $username = $entries['DB_USERNAME']['value'] ?? null;
        $password = array_key_exists('DB_PASSWORD', $entries)
            ? $entries['DB_PASSWORD']['value']
            : SystemSettings::get('DB_PASSWORD');

        try {
            $dsn = match ($driver) {
                'pgsql' => sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port ?: 5432, $database),
                'sqlsrv' => sprintf('sqlsrv:Server=%s,%s;Database=%s', $host, $port ?: 1433, $database),
                default => sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port ?: 3306, $database),
            };

            new PDO($dsn, (string) $username, (string) $password, [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('No se pudo validar la conexión de base de datos desde ajustes del sistema.', [
                'driver' => $driver,
                'host' => $host,
                'database' => $database,
                'error' => $exception->getMessage(),
            ]);

            $this->addError(
                'database',
                'No se pudo conectar a la base de datos con las credenciales indicadas. Verifica los datos e inténtalo nuevamente.',
            );

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function isSecret(array $field): bool
    {
        return (bool) ($field['secret'] ?? false);
    }
}
