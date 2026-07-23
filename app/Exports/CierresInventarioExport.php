<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CierresInventarioExport implements FromCollection, WithHeadings, WithMapping
{
    protected $cierres;

    public function __construct(Collection $cierres)
    {
        $this->cierres = $cierres;
    }

    public function collection(): Collection
    {
        return $this->cierres;
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Tipo',
            'Bodega',
            'Productos',
            'Stock Actual',
            'Esperado',
            'Diferencia',
            'Estado',
            'Usuario',
        ];
    }

    public function map($cierre): array
    {
        return [
            $cierre->closure_date,
            $cierre->type,
            $cierre->almacen?->nombre ?? 'General',
            $cierre->total_products,
            $cierre->total_stock,
            $cierre->expected_stock,
            $cierre->difference,
            $cierre->status,
            $cierre->user?->name,
        ];
    }
}
