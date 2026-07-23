<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventariosExport implements FromCollection, WithHeadings, WithMapping
{
    protected $inventarios;

    protected $tipoReporte;

    public function __construct(Collection $inventarios, string $tipoReporte = 'Consolidado (Todas las bodegas)')
    {
        $this->inventarios = $inventarios;
        $this->tipoReporte = $tipoReporte;
    }

    public function collection(): Collection
    {
        return $this->inventarios;
    }

    public function headings(): array
    {
        return [
            'Tipo de Reporte',
            'Producto',
            'SKU',
            'Almacén',
            'Cantidad',
            'Stock Mínimo',
            'Ubicación',
        ];
    }

    public function map($inventario): array
    {
        return [
            $this->tipoReporte,
            $inventario->producto?->nombre ?? '',
            $inventario->producto?->sku ?? '',
            $inventario->almacen?->nombre ?? '',
            $inventario->cantidad,
            $inventario->cantidad_minima,
            $inventario->ubicacion ?? '',
        ];
    }
}
