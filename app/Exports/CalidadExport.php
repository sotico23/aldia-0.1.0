<?php

namespace App\Exports;

use App\Models\ControlCalidad;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CalidadExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return ControlCalidad::orderBy('fecha', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Lote',
            'Producto',
            'Tipo',
            'Resultado',
            'Cant. Muestra',
            'Cant. Defectuosa',
            'Observaciones',
            'Fecha',
        ];
    }

    public function map($calidad): array
    {
        return [
            $calidad->lote,
            $calidad->producto,
            $calidad->tipo,
            $calidad->resultado,
            $calidad->cantidad_muestra,
            $calidad->cantidad_defectuosa,
            $calidad->observaciones,
            $calidad->fecha?->format('Y-m-d'),
        ];
    }
}
