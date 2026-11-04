<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CierresCajaExport
    implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $cierres) {}

    public function collection(): Collection
    {
        return $this->cierres;
    }

    public function headings(): array
    {
        return [
            'Fecha Desde',
            'Fecha Hasta',
            'Total Efectivo',
            'Total Tarjeta',
            'Total Transferencia',
            'Total Otros',
            'Total General',
            'Cantidad Ventas',
            'Referencia',
            'Archivo Origen',
            'Importado At',
        ];
    }

    public function map($cierre): array
    {
        return [
            $cierre->fecha_desde?->format('Y-m-d'),
            $cierre->fecha_hasta?->format('Y-m-d'),
            $cierre->total_efectivo,
            $cierre->total_tarjeta,
            $cierre->total_transferencia,
            $cierre->total_otros,
            $cierre->total_general,
            $cierre->cantidad_ventas,
            $cierre->referencia,
            $cierre->archivo_origen,
            $cierre->importado_at?->toDateTimeString(),
        ];
    }
}
