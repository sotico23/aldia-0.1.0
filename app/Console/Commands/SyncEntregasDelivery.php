<?php

namespace App\Console\Commands;

use App\Models\Entrega;
use App\Scopes\OwnerScope;
use App\Services\EntregaPedidoService;
use Illuminate\Console\Command;

class SyncEntregasDelivery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delivery:sync-entregas {--owner= : Empresa a sincronizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vincula entregas pendientes con los pedidos de la API movil';

    /**
     * Execute the console command.
     */
    public function handle(EntregaPedidoService $service): int
    {
        $count = 0;
        Entrega::withoutGlobalScope(OwnerScope::class)
            ->whereNull('pedido_id')
            ->libre()
            ->whereIn('estado', ['pendiente', 'en_ruta'])
            ->when($this->option('owner'), fn ($query, $owner) => $query->where('owner_id', $owner))
            ->chunkById(100, function ($entregas) use ($service, &$count): void {
                foreach ($entregas as $entrega) {
                    $service->sync($entrega);
                    $count++;
                }
            });
        $this->info("Entregas sincronizadas: {$count}");

        return self::SUCCESS;
    }
}
