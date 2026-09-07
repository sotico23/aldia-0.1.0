<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Models\Pedido;
use App\Models\User;
use App\Notifications\DeliverySaturacionNotification;
use App\Scopes\OwnerScope;
use App\Services\ZoneAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DeliveryAutoAssign extends Command
{
    protected $signature = 'delivery:auto-assign';

    protected $description = 'Asigna automaticamente los pedidos del pool a la mejor zona y repartidor (capacidad + proximidad)';

    /**
     * Minutos entre alertas de saturacion por tenant, para evitar notificar en cada corrida del scheduler.
     */
    private const ALERTA_COOLDOWN_MINUTES = 30;

    public function handle(ZoneAssignment $assignment): int
    {
        $stats = $assignment->assignPending();

        $this->info("Pedidos procesados: {$stats['procesados']}");
        $this->info("Asignados: {$stats['asignados']}");
        $this->info("Sin zona que los cubra: {$stats['sin_zona']}");
        $this->info("Zona llena: {$stats['zona_llena']}");
        $this->info("Sin repartidor disponible: {$stats['sin_repartidor']}");

        $this->alertarSaturacion();

        return self::SUCCESS;
    }

    /**
     * Notifica a cada tenant que tenga pedidos en el pool sin repartidor, con un
     * cooldown por tenant para no saturar el canal de notificaciones.
     */
    private function alertarSaturacion(): void
    {
        Pedido::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->disponibleEnPool()
            ->whereNotNull('destino_lat')
            ->whereNotNull('destino_lng')
            ->selectRaw('owner_id, count(*) as total')
            ->groupBy('owner_id')
            ->get()
            ->each(function (Pedido $row): void {
                $ownerId = (int) $row->owner_id;
                $pendientes = (int) $row->total;

                if (! Cache::add("zones.saturacion.alerta.{$ownerId}", now()->timestamp, self::ALERTA_COOLDOWN_MINUTES * 60)) {
                    return;
                }

                $owner = User::query()->find($ownerId);

                if ($owner === null) {
                    return;
                }

                NotificationHelper::send($owner, new DeliverySaturacionNotification($ownerId, $pendientes));
                $this->info("Alerta de saturacion enviada al tenant {$ownerId}: {$pendientes} pedido(s) en pool.");
            });
    }
}
