<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LotesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $lotes;

    public function __construct(Collection $lotes)
    {
        $this->lotes = $lotes;
    }

    public function collection(): Collection
    {
        return $this->lotes;
    }

    public function headings(): array
    {
        return [
            'Número Lote',
            'Producto',
            'Cantidad',
            'Fecha Vencimiento',
            'Estado',
            'Notas',
        ];
    }

    public function map($lote): array
    {
        return [
            $lote->numero_lote,
            $lote->producto,
            $lote->cantidad,
            $lote->fecha_vencimiento ? $lote->fecha_vencimiento->format('Y-m-d') : '',
            $lote->estado,
            $lote->notas,
        ];
    }
}
