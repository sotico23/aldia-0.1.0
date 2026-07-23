<?php

namespace App\Imports;

use App\Models\Hito;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class HitosImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Hito([
            'nombre' => $row['nombre'] ?? null,
            'descripcion' => $row['descripcion'] ?? null,
            'fecha_prevista' => $row['fecha_prevista'] ?? null,
            'fecha_real' => $row['fecha_real'] ?? null,
            'estado' => $row['estado'] ?? 'pendiente',
        ]);
    }
}
