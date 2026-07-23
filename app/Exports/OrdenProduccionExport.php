<?php

namespace App\Exports;

use App\Models\OrdenProduccion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdenProduccionExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return OrdenProduccion::orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Número',
            'Producto',
            'Cantidad',
            'Fecha Inicio',
            'Fecha Fin',
            'Progreso (%)',
            'Estado',
            'Notas',
            'Fecha Creación',
        ];
    }

    public function map($orden): array
    {
        return [
            $orden->numero,
            $orden->producto,
            $orden->cantidad,
            $orden->fecha_inicio?->format('Y-m-d'),
            $orden->fecha_fin?->format('Y-m-d'),
            $orden->progreso,
            $orden->estado,
            $orden->notas,
            $orden->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
