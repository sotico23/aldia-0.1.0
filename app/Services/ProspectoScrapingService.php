<?php

namespace App\Services;

use App\Models\Prospecto;
use App\Models\ProspectoScrapeRun;
use App\Scopes\OwnerScope;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProspectoScrapingService
{
    /** @return array{node: string, chrome: string} */
    public function binaries(): array
    {
        $node = (string) SystemSettings::get('SCRAPING_NODE_BINARY', config('system_settings.groups.prospecting.fields.SCRAPING_NODE_BINARY.default', 'node'));
        $chrome = (string) SystemSettings::get('SCRAPING_CHROME_BINARY', config('system_settings.groups.prospecting.fields.SCRAPING_CHROME_BINARY.default', ''));
        if ($chrome === '') {
            foreach (['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome', 'C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'] as $candidate) {
                if (is_file($candidate)) {
                    $chrome = $candidate;
                    break;
                }
            }
        }

        return ['node' => $node ?: 'node', 'chrome' => $chrome];
    }

    /** @return array{ready: bool, queue_ready: bool, queue_connection: string, message: string} */
    public function health(): array
    {
        $binaries = $this->binaries();
        $runtime = Cache::remember('prospecting-runtime:'.hash('sha256', json_encode($binaries)), 60, function () use ($binaries): bool {
            if ($binaries['chrome'] === '') {
                return false;
            }
            try {
                $node = Process::timeout(5)->run([$binaries['node'], '--version']);
                $chrome = Process::timeout(5)->run([$binaries['chrome'], '--version']);

                return $node->successful() && preg_match('/v(\d+)/', $node->output(), $version)
                    && (int) $version[1] >= 22 && $chrome->successful();
            } catch (Throwable) {
                return false;
            }
        });
        $connection = (string) config('queue.default');
        $queueReady = in_array($connection, ['database', 'redis'], true) && (int) config("queue.connections.{$connection}.retry_after", 0) >= 300;

        return ['ready' => $runtime, 'queue_ready' => $queueReady, 'queue_connection' => config('queue.default'),
            'message' => ! $runtime ? 'Configura Node.js 22 o superior y Chrome/Chromium en el servidor.'
                : (! $queueReady ? 'Guarda la configuración con cola Base de datos o Redis (reserva de 300 segundos) y activa el worker de prospecting con timeout de 240 segundos.' : 'Motor disponible. Las búsquedas se procesan en segundo plano.'),
        ];
    }

    /** @return array<string, mixed> */
    public function state(int $ownerId, bool $canConfigure = false): array
    {
        $runs = ProspectoScrapeRun::where('owner_id', $ownerId)->latest('id')->limit(5)->get();

        return ['health' => $this->health(), 'binaries' => $canConfigure ? $this->binaries() : null,
            'runs' => $runs->map(fn (ProspectoScrapeRun $run) => [
                'id' => $run->id, 'query' => $run->query, 'location' => $run->location,
                'max_results' => $run->max_results, 'status' => $run->status, 'error' => $run->error,
                'created_at' => $run->created_at?->toISOString(), 'results' => $run->results ?? [],
            ])->all(),
        ];
    }

    /** @return array{status: string, results: array<int, array<string, string|null>>, error: ?string} */
    public function scrape(ProspectoScrapeRun $run): array
    {
        $binaries = $this->binaries();
        $process = Process::timeout(220)->input(json_encode(['query' => $run->query, 'location' => $run->location, 'limit' => $run->max_results], JSON_THROW_ON_ERROR))
            ->run([$binaries['node'], resource_path('js/scraping/google-maps.mjs'), $binaries['chrome']]);
        $payload = json_decode($process->output(), true);
        if (! $process->successful() || ! is_array($payload) || ! in_array($payload['status'] ?? '', ['completed', 'blocked', 'failed'], true)) {
            throw new RuntimeException('No se pudo ejecutar el navegador. Revisa los binarios, permisos y acceso a Internet del worker.');
        }
        $results = [];
        foreach (array_slice($payload['results'] ?? [], 0, $run->max_results) as $row) {
            if (! is_array($row) || blank($row['name'] ?? null)) {
                continue;
            }
            $url = $this->safeUrl($row['maps_url'] ?? null, true);
            if ($url === null) {
                continue;
            }
            $identity = filled($row['place_id'] ?? null) ? (string) $row['place_id'] : Str::lower(trim($row['name']).'|'.trim($row['address'] ?? ''));
            $key = hash('sha256', $identity);
            $results[$key] = ['id' => $key, 'name' => Str::limit((string) $row['name'], 255, ''),
                'address' => Str::limit((string) ($row['address'] ?? ''), 255, ''),
                'phone' => Str::limit((string) ($row['phone'] ?? ''), 50, ''),
                'category' => Str::limit((string) ($row['category'] ?? ''), 255, ''),
                'website' => $this->safeUrl($row['website'] ?? null), 'maps_url' => $url];
        }

        return ['status' => $payload['status'], 'results' => array_values($results),
            'error' => $payload['status'] === 'completed' ? null : ($payload['status'] === 'blocked'
                ? 'Google solicitó una verificación o bloqueó la consulta. No se intentó eludirla. Prueba más tarde.'
                : 'No se pudo leer Google Maps. Revisa la conexión del servidor e intenta una búsqueda más específica.'),
        ];
    }

    /** @param array<int, string> $ids
     * @return array{created: int, skipped: int}
     */
    public function import(ProspectoScrapeRun $run, array $ids): array
    {
        if ($run->status !== 'completed') {
            throw ValidationException::withMessages(['ids' => 'La búsqueda debe estar completada para importar.']);
        }
        $rows = collect($run->results)->whereIn('id', $ids);
        if ($rows->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'La selección no corresponde a los resultados de esta búsqueda.']);
        }

        return DB::transaction(function () use ($run, $rows): array {
            $created = 0;
            foreach ($rows as $row) {
                $existing = Prospecto::withoutGlobalScope(OwnerScope::class)->where('owner_id', $run->owner_id)
                    ->where(fn ($query) => $query->where('google_maps_key', $row['id'])
                        ->when(filled($row['address']), fn ($query) => $query->orWhere(fn ($query) => $query->where('empresa', $row['name'])->where('direccion', $row['address']))))
                    ->exists();
                if ($existing) {
                    continue;
                }
                $prospect = Prospecto::withoutGlobalScope(OwnerScope::class)->firstOrCreate(
                    ['owner_id' => $run->owner_id, 'google_maps_key' => $row['id']],
                    ['nombre' => $row['name'], 'empresa' => $row['name'], 'telefono' => $row['phone'] ?: null,
                        'direccion' => $row['address'] ?: null, 'giro' => $row['category'] ?: null,
                        'sitio_web' => $row['website'], 'google_maps_url' => $row['maps_url'],
                        'fuente' => 'google_maps', 'estado' => 'nuevo', 'prioridad' => 'media',
                        'notas' => 'Importado desde Google Maps. Búsqueda: '.$run->query.' / '.$run->location],
                );
                $created += (int) $prospect->wasRecentlyCreated;
            }

            return ['created' => $created, 'skipped' => $rows->count() - $created];
        });
    }

    private function safeUrl(?string $url, bool $maps = false): ?string
    {
        if (! $url || mb_strlen($url) > 4000 || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return null;
        }
        if ($maps && ! in_array(parse_url($url, PHP_URL_HOST), ['www.google.com', 'maps.google.com', 'google.com'], true)) {
            return null;
        }

        return $url;
    }
}
