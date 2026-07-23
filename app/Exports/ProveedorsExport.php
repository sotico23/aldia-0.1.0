<?php

namespace App\Exports;

use App\Models\Proveedor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProveedorsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function __construct(protected $collection = null) {}

    public function collection()
    {
        return $this->collection ?? Proveedor::where('owner_id', auth()->user()->getOwnerId())->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'NIT/RUT',
            'Teléfono',
            'Email',
            'Dirección',
            'Activo',
            'Notas',
        ];
    }

    public function map($proveedor): array
    {
        return [
            $proveedor->id,
            $proveedor->nombre,
            $proveedor->nit,
            $proveedor->telefono,
            $proveedor->email,
            $proveedor->direccion,
            $proveedor->activo ? 'Si' : 'No',
            $proveedor->notas,
        ];
    }
}
