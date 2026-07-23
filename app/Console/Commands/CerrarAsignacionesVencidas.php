<?php

namespace App\Console\Commands;

use App\Models\GrupoTrabajoAsignacion;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CerrarAsignacionesVencidas extends Command
{
    protected $signature = 'grupos:cerrar-asignaciones';

    protected $description = 'Cierra automáticamente las asignaciones de grupos de trabajo cuyo período ha expirado';

    public function handle(): int
    {
        $hoy = Carbon::now()->toDateString();

        $cerradas = GrupoTrabajoAsignacion::where('estado', 'activa')
            ->where('fecha_fin', '<', $hoy)
            ->update(['estado' => 'completada']);

        if ($cerradas > 0) {
            $this->info("{$cerradas} asignación(es) cerrada(s) automáticamente.");
        } else {
            $this->info('No hay asignaciones pendientes de cierre.');
        }

        return self::SUCCESS;
    }
}
