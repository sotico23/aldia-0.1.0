<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VentasExport implements FromCollection, WithHeadings, WithMapping
{
    protected $ventas;

    public function __construct($ventas)
    {
        $this->ventas = $ventas;
    }

    public function collection()
    {
        return $this->ventas;
    }

    public function headings(): array
    {
        return [
            'Numero',
            'Fecha',
            'Cliente Email',
            'Estado',
            'Item Descripcion',
            'Item Cantidad',
            'Item Precio',
            'Subtotal',
            'IVA',
            'Total',
            'Notas',
        ];
    }

    public function map($venta): array
    {
        $rows = [];
        if ($venta->detalleVentas->isEmpty()) {
            $rows[] = [
                $venta->numero,
                $venta->fecha ? Carbon::parse($venta->fecha)->format('Y-m-d') : '',
                $venta->cliente->email ?? '',
                $venta->estado,
                '',
                '',
                '',
                $venta->subtotal,
                $venta->iva,
                $venta->total,
                $venta->notas,
            ];
        } else {
            foreach ($venta->detalleVentas as $d) {
                $rows[] = [
                    $venta->numero,
                    $venta->fecha ? Carbon::parse($venta->fecha)->format('Y-m-d') : '',
                    $venta->cliente->email ?? '',
                    $venta->estado,
                    $d->producto->nombre ?? 'Producto Eliminado',
                    $d->cantidad,
                    $d->precio_unitario,
                    $venta->subtotal,
                    $venta->iva,
                    $venta->total,
                    $venta->notas,
                ];
            }
        }

        return $rows;
    }
}
