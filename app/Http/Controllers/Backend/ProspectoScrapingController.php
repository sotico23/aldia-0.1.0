<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backend\ConfigureProspectoScrapingRequest;
use App\Http\Requests\Backend\ImportScrapedProspectosRequest;
use App\Http\Requests\Backend\StartProspectoScrapingRequest;
use App\Jobs\ScrapeGoogleMapsProspectos;
use App\Models\ProspectoScrapeRun;
use App\Models\User;
use App\Services\ProspectoScrapingService;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProspectoScrapingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:comercial.prospectos.import', only: ['start', 'cancel', 'importResults']),
            new Middleware('permission:comercial.prospectos.create', only: ['importResults']),
            new Middleware('permission:admin.web-settings.edit', only: ['configure']),
        ];
    }

    public function configure(ConfigureProspectoScrapingRequest $request, ProspectoScrapingService $engine): RedirectResponse
    {
        $settings = [
            'SCRAPING_NODE_BINARY' => ['value' => $request->validated('node_binary') ?: 'node', 'group' => 'prospecting'],
            'SCRAPING_CHROME_BINARY' => ['value' => $request->validated('chrome_binary') ?: '', 'group' => 'prospecting'],
        ];
        if ($request->has('queue_connection')) {
            $settings['QUEUE_CONNECTION'] = ['value' => $request->validated('queue_connection'), 'group' => 'prospecting'];
            $retryKey = $request->validated('queue_connection') === 'redis' ? 'REDIS_QUEUE_RETRY_AFTER' : 'DB_QUEUE_RETRY_AFTER';
            $settings[$retryKey] = ['value' => '300', 'group' => 'prospecting'];
        }
        SystemSettings::save($settings);
        if ($request->has('queue_connection')) {
            $connection = $request->validated('queue_connection');
            config(['queue.default' => $connection, "queue.connections.{$connection}.retry_after" => 300]);
        }
        $health = $engine->health(refresh: true);

        return $health['ready'] && $health['queue_ready']
            ? back()->with('success', 'Configuración guardada y requisitos comprobados. Ya puedes buscar negocios.')
            : back()->with('warning', 'Configuración guardada, pero falta un requisito: '.$health['message']);
    }

    public function check(Request $request, ProspectoScrapingService $engine): RedirectResponse
    {
        $health = $engine->health(refresh: true);

        return $health['ready'] && $health['queue_ready']
            ? back()->with('success', 'Comprobación terminada. Los requisitos están disponibles.')
            : back()->with('warning', 'Comprobación terminada: '.$health['message']);
    }

    public function start(StartProspectoScrapingRequest $request, ProspectoScrapingService $engine): RedirectResponse
    {
        $health = $engine->health();
        if (! $health['ready'] || ! $health['queue_ready']) {
            throw ValidationException::withMessages(['query' => $health['message']]);
        }
        $ownerId = $request->user()->getOwnerId();
        $createdRunId = null;
        try {
            DB::transaction(function () use ($request, $ownerId, $health, &$createdRunId): void {
                User::whereKey($ownerId)->lockForUpdate()->firstOrFail();
                if (ProspectoScrapeRun::where('owner_id', $ownerId)->whereIn('status', ['queued', 'running'])->exists()) {
                    throw ValidationException::withMessages(['query' => 'Ya hay una búsqueda pendiente. Espera o cancélala antes de iniciar otra.']);
                }
                $run = ProspectoScrapeRun::create([...$request->validated(), 'owner_id' => $ownerId, 'user_id' => $request->user()->id, 'status' => 'queued']);
                $createdRunId = $run->id;
                ScrapeGoogleMapsProspectos::dispatch($run->id)->onConnection($health['queue_connection'])->onQueue('prospecting');
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            if ($createdRunId !== null) {
                ProspectoScrapeRun::whereKey($createdRunId)->where('owner_id', $ownerId)->where('status', 'queued')
                    ->update(['status' => 'failed', 'finished_at' => now(), 'error' => 'No se pudo enviar el trabajo a la cola. Revisa su conexión.']);
            }
            throw ValidationException::withMessages(['query' => 'No se pudo encolar la búsqueda. Revisa la conexión de colas y su tabla de trabajos antes de intentar nuevamente.']);
        }

        return back()->with('success', 'Búsqueda enviada al motor. Los resultados aparecerán para revisión.');
    }

    public function cancel(Request $request, int $run): RedirectResponse
    {
        $this->ownedRun($request, $run)->newQuery()->whereKey($run)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'cancelled', 'finished_at' => now()]);

        return back()->with('success', 'Búsqueda cancelada.');
    }

    public function importResults(ImportScrapedProspectosRequest $request, int $run, ProspectoScrapingService $engine): RedirectResponse
    {
        $counts = $engine->import($this->ownedRun($request, $run), $request->validated('ids'));

        return back()->with('success', "Se crearon {$counts['created']} prospectos y se omitieron {$counts['skipped']} duplicados.");
    }

    private function ownedRun(Request $request, int $id): ProspectoScrapeRun
    {
        return ProspectoScrapeRun::where('owner_id', $request->user()->getOwnerId())->findOrFail($id);
    }
}
