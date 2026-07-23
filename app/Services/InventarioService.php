<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    public function agregarStock(int $productoId, float $cantidad, string $ubicacion, ?string $referencia = null): Inventario
    {
        return DB::transaction(function () use ($productoId, $cantidad, $ubicacion, $referencia) {
            $inventario = Inventario::updateOrCreate(
                ['producto_id' => $productoId],
                ['ubicacion' => $ubicacion]
            );

            $inventario->increment('cantidad', $cantidad);

            Movimiento::create([
                'tipo' => 'entrada',
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'referencia' => $referencia ?? 'Entrada manual',
                'fecha' => now(),
            ]);

            return $inventario;
        });
    }

    public function reducirStock(int $productoId, float $cantidad, string $referencia): Inventario
    {
        return DB::transaction(function () use ($productoId, $cantidad, $referencia) {
            $inventario = Inventario::where('producto_id', $productoId)->firstOrFail();

            if ($inventario->cantidad < $cantidad) {
                throw new \Exception('Stock insuficiente');
            }

            $inventario->decrement('cantidad', $cantidad);

            Movimiento::create([
                'tipo' => 'salida',
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'referencia' => $referencia,
                'fecha' => now(),
            ]);

            return $inventario;
        });
    }

    public function getProductosBajoStock(): array
    {
        return Inventario::with('producto')
            ->whereColumn('cantidad', '<=', 'cantidad_minima')
            ->orWhereDoesntHave('producto')
            ->get()
            ->map(fn ($item) => [
                'producto_id' => $item->producto_id,
                'nombre' => $item->producto?->nombre ?? 'N/A',
                'cantidad_actual' => $item->cantidad,
                'cantidad_minima' => $item->cantidad_minima,
                'ubicacion' => $item->ubicacion,
            ])
            ->toArray();
    }

    public function getResumenInventario(): array
    {
        $totalProductos = Producto::count();
        $productosActivos = Producto::where('activo', true)->count();
        $bajoStock = count($this->getProductosBajoStock());
        $valorTotal = Producto::with('inventario')
            ->get()
            ->sum(fn ($p) => ($p->inventario?->cantidad ?? 0) * $p->precio_compra);

        return [
            'total_productos' => $totalProductos,
            'productos_activos' => $productosActivos,
            'productos_bajo_stock' => $bajoStock,
            'valor_total_inventario' => $valorTotal,
        ];
    }
}
