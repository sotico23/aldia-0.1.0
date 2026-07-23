<?php

namespace App\Console\Commands;

use App\Jobs\CheckUptimeJob;
use App\Models\MonitoredSite;
use Illuminate\Console\Command;

class CheckUptimeCommand extends Command
{
    protected $signature = 'uptime:check {--site= : ID del sitio específico}';

    protected $description = 'Ejecuta chequeos de uptime para todos los sitios activos';

    public function handle(): int
    {
        $siteId = $this->option('site');

        if ($siteId) {
            $sites = MonitoredSite::where('id', $siteId)->where('is_active', true)->get();
            if ($sites->isEmpty()) {
                $this->error("Sitio con ID {$siteId} no encontrado o no está activo.");

                return Command::FAILURE;
            }
        } else {
            $sites = MonitoredSite::where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('last_check_at')
                        ->orWhere('last_check_at', '<=', now()->subMinutes(5));
                })
                ->get();
        }

        if ($sites->isEmpty()) {
            $this->info('No hay sitios que necesiten ser verificados.');

            return Command::SUCCESS;
        }

        $this->info("Verificando {$sites->count()} sitio(s)...");

        $bar = $this->output->createProgressBar($sites->count());
        $bar->start();

        foreach ($sites as $site) {
            CheckUptimeJob::dispatchSync($site);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Verificación completada.');

        return Command::SUCCESS;
    }
}
