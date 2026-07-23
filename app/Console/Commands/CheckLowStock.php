<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Models\Inventario;
use App\Models\User;
use App\Notifications\StockLowNotification;
use Illuminate\Console\Command;

class CheckLowStock extends Command
{
    protected $signature = 'stock:check-low';

    protected $description = 'Notify users with inventory access about products below minimum stock';

    public function handle(): int
    {
        $productos = Inventario::with('producto')
            ->whereColumn('cantidad', '<=', 'cantidad_minima')
            ->where('cantidad_minima', '>', 0)
            ->get()
            ->map(fn ($inv) => [
                'producto_id' => $inv->producto_id,
                'nombre' => $inv->producto?->nombre ?? 'N/A',
                'cantidad_actual' => (float) $inv->cantidad,
                'cantidad_minima' => (float) $inv->cantidad_minima,
                'almacen_id' => $inv->almacen_id,
            ])
            ->toArray();

        if (empty($productos)) {
            $this->info('No hay productos con stock bajo.');

            return Command::SUCCESS;
        }

        $users = User::permission('inventario.inventarios.viewAny')->get();

        foreach ($users as $user) {
            NotificationHelper::send($user, new StockLowNotification($productos));
        }

        $this->info('Notificación de stock bajo enviada a '.$users->count().' usuario(s).');

        return Command::SUCCESS;
    }
}
