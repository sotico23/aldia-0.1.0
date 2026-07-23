<?php

namespace App\Imports;

use App\Models\Entrega;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EntregasImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Entrega([
            'owner_id' => Auth::user()->getOwnerId(),
            'venta_id' => $row['venta_id'] ?? null,
            'vehiculo_id' => $row['vehiculo_id'] ?? null,
            'conductor_id' => $row['conductor_id'] ?? null,
            'cliente' => $row['cliente'] ?? null,
            'direccion' => $row['direccion'] ?? null,
            'fecha_entrega' => isset($row['fecha_entrega']) ? Carbon::parse($row['fecha_entrega']) : null,
            'estado' => $row['estado'] ?? 'pendiente',
            'notas' => $row['notas'] ?? null,
            'descripcion' => $row['descripcion'] ?? null,
        ]);
    }
}
