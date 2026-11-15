<?php

namespace App\Jobs;

use App\Models\ProspectoScrapeRun;
use App\Services\ProspectoScrapingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ScrapeGoogleMapsProspectos implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 1;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId)
    {
        $this->afterCommit();
    }

    /**
     * Execute the job.
     */
    public function handle(ProspectoScrapingService $engine): void
    {
        $run = ProspectoScrapeRun::find($this->runId);
        if (! $run || ! ProspectoScrapeRun::whereKey($run->id)->where('status', 'queued')->update(['status' => 'running', 'started_at' => now()])) {
            return;
        }
        try {
            $result = Cache::lock('prospecting-browser', 230)->block(5, fn () => $engine->scrape($run));
            ProspectoScrapeRun::whereKey($run->id)->where('status', 'running')->update([
                'status' => $result['status'], 'results' => json_encode($result['results'], JSON_THROW_ON_ERROR),
                'error' => $result['error'], 'finished_at' => now(),
            ]);
        } catch (Throwable) {
            $this->failed(null);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ProspectoScrapeRun::whereKey($this->runId)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed', 'finished_at' => now(),
            'error' => 'No se pudo terminar la búsqueda. Revisa el motor, el worker y la conexión; también puede haber otra búsqueda en curso.',
        ]);
    }
}
