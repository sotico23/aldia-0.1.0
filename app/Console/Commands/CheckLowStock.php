<?php

namespace App\Console\Commands;

use App\Events\LowStock;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLowStock extends Command
{
    protected $signature = 'stock:check-low';
    protected $description = 'Verifica productos con stock bajo y dispara eventos LowStock';

    public function handle(): int
    {
        $lowStockProducts = Inventario::with(['producto', 'almacen'])
            ->whereColumn('cantidad', '<=', 'cantidad_minima')
            ->where('cantidad', '>', 0)
            ->get();

        $count = 0;

        foreach ($lowStockProducts as $inventario) {
            $producto = $inventario->producto;

            if (! $producto) {
                continue;
            }

            event(new LowStock(
                $producto,
                $inventario,
                (int) $inventario->cantidad,
                (int) $inventario->cantidad_minima
            ));

            $count++;

            Log::info('LowStock event dispatched', [
                'producto_id' => $producto->id,
                'producto_nombre' => $producto->nombre,
                'stock_actual' => $inventario->cantidad,
                'stock_minimo' => $inventario->cantidad_minima,
                'almacen_id' => $inventario->almacen_id,
            ]);
        }

        $this->info("Verificados {$count} productos con stock bajo.");

        return self::SUCCESS;
    }
}