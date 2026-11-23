<?php

namespace App\Livewire;

use App\Services\SystemSettingsConnectionCheck;
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

    public function save(?string $group = null): void
    {
        $fields = $group === null ? SystemSettings::fields() : $this->fieldsForGroup($group);

        $this->validate($this->rules($fields));

        $entries = $this->buildEntries($fields);

        if (isset($fields['DB_CONNECTION']) && $this->databaseOverrideEnabled && ! $this->testDatabaseConnection($entries)) {
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

        session()->flash(
            $group === null ? 'system-settings-status' : "system-settings-group-status.{$group}",
            'Configuración guardada correctamente.',
        );
    }

    public function checkConnection(string $group, SystemSettingsConnectionCheck $check): void
    {
        $fields = $this->fieldsForGroup($group);
        abort_if($group === 'app', 404);

        $this->resetValidation("connection.{$group}");
        $this->validate($this->rules($fields, testingConnection: true));

        $entries = $this->buildEntries($fields);

        if ($group === 'database') {
            $this->resetValidation('database');

            if ($this->testDatabaseConnection($entries)) {
                session()->flash('system-settings-group-status.database', 'Conexión a la base de datos verificada.');
            }

            return;
        }

        $values = [];

        foreach ($fields as $key => $field) {
            $value = array_key_exists($key, $entries)
                ? $entries[$key]['value']
                : SystemSettings::effectiveValue($key);
            $target = $field['config'][0] ?? null;
            $values[$key] = $value ?? $field['default'] ?? ($target === null ? null : config($target));
        }

        try {
            $message = $check->check($group, $values);
        } catch (Throwable) {
            $message = null;
        }

        if ($message === null) {
            $this->addError("connection.{$group}", match ($group) {
                'mail' => 'No se pudo establecer la conexión SMTP. Selecciona SMTP y revisa el host, puerto y las credenciales.',
                'aws' => 'No se pudo acceder al bucket. Revisa la región, las credenciales y el permiso s3:ListBucket.',
                'sii' => 'No se pudo verificar el SII. Revisa que las URL pertenezcan a sii.cl y usen HTTPS.',
                default => 'No se pudo verificar la conexión. Revisa la configuración e inténtalo nuevamente.',
            });

            return;
        }

        session()->flash("system-settings-group-status.{$group}", $message);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fieldsForGroup(string $group): array
    {
        abort_unless(array_key_exists($group, SystemSettings::groups()), 404);

        return array_filter(SystemSettings::fields(), fn (array $field): bool => $field['group'] === $group);
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
            'googleMapsKey' => SystemSettings::currentValues(['VITE_GOOGLE_MAPS_API_KEY'])['VITE_GOOGLE_MAPS_API_KEY'],
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

        if (isset($fields['DB_CONNECTION'])) {
            $entries[SystemSettings::DB_OVERRIDE_KEY] = [
                'value' => $this->databaseOverrideEnabled ? '1' : '0',
                'secret' => false,
                'group' => 'database',
            ];
        }

        return $entries;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function rules(array $fields, bool $testingConnection = false): array
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
                'url' => ['nullable', 'url', 'max:2048'],
                'select' => ['nullable', 'string', Rule::in(array_keys($field['options'] ?? []))],
                default => ['nullable', 'string', 'max:2000'],
            };
        }

        if (isset($fields['PUSHY_SECRET_API_KEY']) && ($this->values['PUSHY_ENABLED'] ?? 'false') === 'true') {
            $rules['secrets.PUSHY_SECRET_API_KEY'][] = Rule::requiredIf(
                blank(SystemSettings::get('PUSHY_SECRET_API_KEY', config('services.pushy.fallback.secret_api_key')))
            );
        }

        if (isset($fields['DB_CONNECTION']) && ($this->databaseOverrideEnabled || $testingConnection)) {
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

        $host = $entries['DB_HOST']['value'] ?? null;
        $port = $entries['DB_PORT']['value'] ?? null;
        $database = $entries['DB_DATABASE']['value'] ?? null;
        $username = $entries['DB_USERNAME']['value'] ?? null;
        $password = array_key_exists('DB_PASSWORD', $entries)
            ? $entries['DB_PASSWORD']['value']
            : SystemSettings::get('DB_PASSWORD', config("database.connections.{$driver}.password"));

        try {
            if ($driver === 'sqlite' && $database !== ':memory:') {
                if (! is_file($database) && ! is_file(base_path($database))) {
                    throw new \RuntimeException('SQLite database does not exist.');
                }

                $database = is_file($database) ? $database : base_path($database);
            }

            $dsn = match ($driver) {
                'sqlite' => 'sqlite:'.$database,
                'pgsql' => sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port ?: 5432, $database),
                'sqlsrv' => sprintf('sqlsrv:Server=%s,%s;Database=%s', $host, $port ?: 1433, $database),
                default => sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port ?: 3306, $database),
            };

            $connection = new PDO($dsn, (string) $username, (string) $password, [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $connection->query('SELECT 1');

            return true;
        } catch (Throwable $exception) {
            Log::warning('No se pudo validar la conexión de base de datos desde ajustes del sistema.', [
                'driver' => $driver,
                'host' => $host,
                'database' => $database,
                'exception' => $exception::class,
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
