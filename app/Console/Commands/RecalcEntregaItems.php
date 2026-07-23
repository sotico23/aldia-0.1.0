<?php

namespace App\Console\Commands;

use App\Models\EntregaItem;
use App\Services\DeliveryStatsService;
use Illuminate\Console\Command;

class RecalcEntregaItems extends Command
{
    protected $signature = 'entregas:recalc-items';

    protected $description = 'Recalculate subtotal_metrica/unidades_totales for all entrega items based on current product config.';

    public function __construct(private DeliveryStatsService $statsService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $items = EntregaItem::with('producto')->get();

        foreach ($items as $item) {
            if (! $item->producto) {
                continue;
            }

            $totals = $this->statsService->calculateItemTotals(
                $item->producto_id,
                (float) $item->cantidad_pedida
            );

            $item->update([
                'subtotal_metrica' => ($totals['kg'] > 0) ? $totals['kg'] : $totals['litros'],
                'unidades_totales' => $totals['unidades'],
            ]);

            $this->line("Item #{$item->id} ({$item->producto->nombre}) {$item->cantidad_pedida} u => metrica=".(($totals['kg'] > 0) ? $totals['kg'] : $totals['litros'])." unid={$totals['unidades']}");
        }

        $this->info('Recalculación completada.');

        return self::SUCCESS;
    }
}
