<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class MaintenanceCommandService
{
    /** @return array{success: bool, command: string, output: string, exit_code: int, executed_at: string} */
    public function execute(string $command, int $userId): array
    {
        $commands = config('maintenance.commands', []);
        abort_unless(isset($commands[$command]), 422, 'Comando no permitido.');

        $lock = Cache::build([
            'driver' => 'file',
            'path' => storage_path('framework/maintenance-locks'),
            'lock_path' => storage_path('framework/maintenance-locks'),
        ])->lock('artisan-command', 900);
        abort_unless($lock->get(), 409, 'Ya se está ejecutando una tarea de mantenimiento.');

        try {
            $output = new BufferedOutput;
            try {
                $exitCode = Artisan::call($command, [
                    ...$commands[$command]['parameters'],
                    '--no-interaction' => true,
                ], $output);
                $text = $output->fetch();
            } catch (Throwable $exception) {
                report($exception);
                $exitCode = 1;
                $text = 'La tarea no pudo completarse. Revisa el registro del servidor para conocer el detalle.';
            }

            Log::notice('Mantenimiento web ejecutado', [
                'user_id' => $userId,
                'command' => $command,
                'exit_code' => $exitCode,
            ]);

            return [
                'success' => $exitCode === 0,
                'command' => $command,
                'output' => mb_substr($text, 0, 20000),
                'exit_code' => $exitCode,
                'executed_at' => now()->toISOString(),
            ];
        } finally {
            $lock->release();
        }
    }
}
