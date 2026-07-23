<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MovimientosExport implements FromCollection, WithHeadings, WithMapping
{
    protected $movimientos;

    public function __construct(Collection $movimientos)
    {
        $this->movimientos = $movimientos;
    }

    public function collection(): Collection
    {
        return $this->movimientos;
    }

    public function headings(): array
    {
        return [
            'Producto',
            'Tipo',
            'Cantidad',
            'Origen',
            'Destino',
            'Referencia',
            'Notas',
            'Fecha',
        ];
    }

    public function map($movimiento): array
    {
        return [
            $movimiento->producto,
            $movimiento->tipo,
            $movimiento->cantidad,
            $movimiento->almacen_origen,
            $movimiento->almacen_destino,
            $movimiento->referencia,
            $movimiento->notas,
            $movimiento->created_at->format('Y-m-d H:i'),
        ];
    }
}
