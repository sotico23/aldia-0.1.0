<?php

namespace App\Exports;

use App\Models\Compra;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ComprasExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function __construct(protected $collection = null) {}

    public function collection()
    {
        return $this->collection ?? Compra::with('proveedor')->where('owner_id', auth()->user()->getOwnerId())->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Número',
            'Proveedor',
            'Fecha',
            'Subtotal',
            'IVA',
            'Total',
            'Estado',
            'Notas',
        ];
    }

    public function map($compra): array
    {
        return [
            $compra->id,
            $compra->numero,
            $compra->proveedor?->nombre ?? 'N/A',
            $compra->fecha,
            $compra->subtotal,
            $compra->iva,
            $compra->total,
            $compra->estado,
            $compra->notas,
        ];
    }
}
