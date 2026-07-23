<?php

namespace App\Exports;

use App\Models\Vacio;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VaciosExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Vacio::with('producto')
            ->where('owner_id', auth()->user()->getOwnerId())
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Producto',
            'Cantidad',
            'Cantidad Mínima',
            'Estado',
            'Ubicación',
            'Observaciones',
        ];
    }

    public function map($vacio): array
    {
        return [
            $vacio->id,
            $vacio->producto?->nombre ?? 'N/A',
            $vacio->cantidad,
            $vacio->cantidad_minima,
            $vacio->estado,
            $vacio->ubicacion,
            $vacio->observaciones,
        ];
    }
}
