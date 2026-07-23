<?php

namespace App\Imports;

use App\Models\Proveedor;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProveedorsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $ownerId = Auth::user()->getOwnerId();

        return Proveedor::updateOrCreate(
            [
                'nombre' => $row['nombre'],
                'owner_id' => $ownerId,
            ],
            [
                'nit' => $row['nitrut'] ?? $row['nit'] ?? $row['rut'] ?? null,
                'telefono' => $row['telefono'] ?? null,
                'email' => $row['email'] ?? null,
                'direccion' => $row['direccion'] ?? null,
                'activo' => (isset($row['activo']) && (strtolower($row['activo']) === 'si' || $row['activo'] === '1' || strtolower($row['activo']) === 'yes' || $row['activo'] === true)) ? 1 : 0,
                'notas' => $row['notas'] ?? null,
            ]
        );
    }
}
