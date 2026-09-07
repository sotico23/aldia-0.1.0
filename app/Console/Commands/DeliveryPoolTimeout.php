<?php

namespace App\Console\Commands;

use App\Services\PoolTimeout;
use Illuminate\Console\Command;

class DeliveryPoolTimeout extends Command
{
    protected $signature = 'delivery:pool-timeout';

    protected $description = 'Libera automaticamente los pedidos aceptados pero no recogidos dentro del timeout del pool';

    public function handle(PoolTimeout $poolTimeout): int
    {
        $stats = $poolTimeout->liberarVencidos();

        $this->info("Pedidos revisados: {$stats['procesados']}");
        $this->info("Liberados por timeout: {$stats['liberados']}");

        return self::SUCCESS;
    }
}
