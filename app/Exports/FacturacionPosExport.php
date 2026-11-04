<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FacturacionPosExport
    implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $documentos) {}

    public function collection(): Collection
    {
        return $this->documentos;
    }

    public function headings(): array
    {
        return ['Venta ID', 'Número', 'Fecha', 'Tipo', 'Folio DTE', 'Tipo DTE', 'Estado', 'Total'];
    }

    public function map($venta): array
    {
        return [
            $venta->id,
            $venta->numero,
            $venta->fecha?->format('Y-m-d'),
            $venta->tipo_documento,
            $venta->folio,
            $venta->tipo_dte,
            $venta->estado,
            $venta->total,
        ];
    }
}
