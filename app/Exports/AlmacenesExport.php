<?php

namespace App\Exports;

use App\Models\Almacen;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AlmacenesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        return Almacen::with('empleados')
            ->where('owner_id', auth()->user()->getOwnerId())
            ->orderBy('nombre')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Codigo',
            'Nombre',
            'Direccion',
            'Telefono',
            'Responsable',
            'Capacidad',
            'Tipo',
            'Activo',
            'Notas',
        ];
    }

    public function map($almacen): array
    {
        return [
            $almacen->id,
            $almacen->codigo,
            $almacen->nombre,
            $almacen->direccion,
            $almacen->telefono,
            $almacen->responsable,
            $almacen->capacidad,
            $almacen->tipo,
            $almacen->activo ? 'Si' : 'No',
            $almacen->notas,
        ];
    }
}
