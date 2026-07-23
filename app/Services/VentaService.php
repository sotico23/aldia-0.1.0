<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VentaService
{
    public function crearVenta(array $datos): Venta
    {
        return DB::transaction(function () use ($datos) {
            $subtotal = 0;
            $detalles = $datos['detalles'] ?? [];

            foreach ($detalles as $detalle) {
                $subtotal += $detalle['cantidad'] * $detalle['precio_unitario'];
            }

            $iva = $subtotal * config('taxes.iva_rate');
            $total = $subtotal + $iva;

            $venta = Venta::create([
                'cliente_id' => $datos['cliente_id'],
                'user_id' => auth()->id(),
                'fecha' => $datos['fecha'] ?? now(),
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total,
                'metodo_pago' => $datos['metodo_pago'] ?? 'efectivo',
                'tipo_documento' => $datos['tipo_documento'] ?? 'boleta',
                'es_pos' => $datos['es_pos'] ?? false,
                'estado' => 'completada',
                'notas' => $datos['notas'] ?? null,
            ]);

            foreach ($detalles as $detalle) {
                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $detalle['producto_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal' => $detalle['cantidad'] * $detalle['precio_unitario'],
                ]);

                $this->actualizarInventario($detalle['producto_id'], $detalle['cantidad']);
            }

            return $venta;
        });
    }

    public function actualizarInventario(int $productoId, int $cantidadVendida): void
    {
        $inventario = Inventario::where('producto_id', $productoId)->first();

        if ($inventario) {
            $inventario->decrement('cantidad', $cantidadVendida);
        }
    }

    public function getVentasDelDia(): Collection
    {
        return Venta::whereDate('fecha', today())
            ->with('cliente', 'detalleVentas.producto')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getVentasPorCliente(int $clienteId): Collection
    {
        return Venta::where('cliente_id', $clienteId)
            ->with('detalleVentas.producto')
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function calcularTotalVentasPeriodo(string $fechaInicio, string $fechaFin): array
    {
        $ventas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('estado', 'pagada')
            ->get();

        return [
            'total' => $ventas->sum('total'),
            'subtotal' => $ventas->sum('subtotal'),
            'iva' => $ventas->sum('iva'),
            'cantidad' => $ventas->count(),
        ];
    }
}
