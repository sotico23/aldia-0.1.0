<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Venta;

class ClienteService
{
    public function crearCliente(array $datos): Cliente
    {
        return Cliente::create($datos);
    }

    public function actualizarCliente(Cliente $cliente, array $datos): Cliente
    {
        $cliente->update($datos);

        return $cliente->fresh();
    }

    public function getResumenCliente(Cliente $cliente): array
    {
        $ventas = Venta::where('cliente_id', $cliente->id)->get();
        $facturas = Factura::where('cliente_id', $cliente->id)->get();

        $totalVentas = $ventas->sum('total');
        $ventasPendientes = $ventas->where('estado', 'pendiente')->count();
        $facturasPendientes = $facturas->where('estado', 'pendiente')->count();
        $ultimaVenta = $ventas->sortByDesc('fecha')->first();

        return [
            'total_ventas' => $ventas->count(),
            'monto_total_ventas' => $totalVentas,
            'ventas_pendientes' => $ventasPendientes,
            'facturas_pendientes' => $facturasPendientes,
            'ultima_venta_fecha' => $ultimaVenta?->fecha,
            'ultima_venta_monto' => $ultimaVenta?->total,
        ];
    }

    public function buscarClientes(string $termino)
    {
        return Cliente::where('nombre', 'like', "%{$termino}%")
            ->orWhere('nit', 'like', "%{$termino}%")
            ->orWhere('email', 'like', "%{$termino}%")
            ->orWhere('telefono', 'like', "%{$termino}%")
            ->limit(20)
            ->get();
    }

    public function getClientesConDeudas(): array
    {
        return Cliente::with(['ventas' => fn ($q) => $q->where('estado', 'pendiente')])
            ->whereHas('ventas', fn ($q) => $q->where('estado', 'pendiente'))
            ->get()
            ->map(fn ($cliente) => [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'nit' => $cliente->nit,
                'total_deuda' => $cliente->ventas->sum('total'),
                'ventas_pendientes' => $cliente->ventas->count(),
            ])
            ->toArray();
    }
}
