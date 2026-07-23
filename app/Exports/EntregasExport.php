<?php

namespace App\Exports;

use App\Models\Entrega;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EntregasExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function query()
    {
        return Entrega::where('owner_id', Auth::user()->getOwnerId())
            ->with(['venta', 'conductor', 'vehiculo']);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Venta ID',
            'Vehículo',
            'Conductor',
            'Cliente',
            'Dirección',
            'Fecha Entrega',
            'Estado',
            'Notas',
            'Descripción',
            'Creado',
        ];
    }

    public function map($entrega): array
    {
        return [
            $entrega->id,
            $entrega->venta_id,
            $entrega->vehiculo?->patente ?? $entrega->vehiculo_id,
            $entrega->conductor?->nombre ?? $entrega->conductor_id,
            $entrega->cliente,
            $entrega->direccion,
            $entrega->fecha_entrega ? $entrega->fecha_entrega->format('Y-m-d') : '',
            $entrega->estado,
            $entrega->notas,
            $entrega->descripcion,
            $entrega->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
