<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Models\ProgramacionCallCenter;
use App\Models\User;
use App\Notifications\RecordatorioLlamadaNotification;
use Illuminate\Console\Command;

class SendRecordatoriosCallCenter extends Command
{
    protected $signature = 'call-center:send-recordatorios';

    protected $description = 'Send reminders for upcoming scheduled calls';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        ProgramacionCallCenter::query()
            ->where('completada', false)
            ->whereNull('notificado_at')
            ->where('fecha_programada', '>', $now)
            ->where('fecha_programada', '<=', $now->copy()->addMinutes(30))
            ->chunk(100, function ($programaciones) use ($now, &$sent) {
                foreach ($programaciones as $programacion) {
                    $minutosRestantes = $now->diffInMinutes($programacion->fecha_programada, false);

                    if ($minutosRestantes <= $programacion->recordatorio_minutos) {
                        $user = User::find($programacion->user_id);
                        if ($user) {
                            NotificationHelper::send($user, new RecordatorioLlamadaNotification($programacion));
                            $programacion->update(['notificado_at' => $now]);
                            $sent++;
                        }
                    }
                }
            });

        $this->info("Sent {$sent} reminder(s).");

        return Command::SUCCESS;
    }
}
