<?php

namespace App\Actions\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class DeductInventoryAction
{
    /**
     * Deduct inventory for given products and quantities.
     *
     * @param  array  $productIds  Array of product IDs
     * @param  array  $quantities  Array of quantities matching product IDs
     * @param  array  $almacenIds  Array of warehouse IDs to deduct from
     *
     * @throws InsufficientStockException
     */
    public function __invoke(array $productIds, array $quantities, array $almacenIds): void
    {
        DB::transaction(function () use ($productIds, $quantities, $almacenIds) {
            foreach ($productIds as $index => $productoId) {
                $cantidadRequerida = (float) ($quantities[$index] ?? 0);

                foreach ($almacenIds as $almacenId) {
                    if ($cantidadRequerida <= 0) {
                        break;
                    }

                    $inventario = Inventario::where('producto_id', $productoId)
                        ->where('almacen_id', $almacenId)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventario) {
                        continue;
                    }

                    $aDescontar = min($cantidadRequerida, (float) $inventario->cantidad);
                    $inventario->decrement('cantidad', $aDescontar);
                    $cantidadRequerida -= $aDescontar;
                }

                if ($cantidadRequerida > 0) {
                    $nombreProducto = Producto::where('id', $productoId)->value('nombre') ?? "Producto #{$productoId}";
                    throw new InsufficientStockException(
                        productName: $nombreProducto,
                        requested: (float) ($quantities[$index] ?? 0),
                        available: (float) (($quantities[$index] ?? 0) - $cantidadRequerida),
                        productId: (int) $productoId
                    );
                }
            }
        });
    }
}
