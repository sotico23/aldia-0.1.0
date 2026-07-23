<?php

namespace App\Imports;

use App\Models\ControlCalidad;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CalidadImport implements ToModel, WithHeadingRow
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        $lote = $row['lote'] ?? null;
        if (! $lote) {
            return null;
        }

        return new ControlCalidad([
            'lote' => $lote,
            'producto' => $row['producto'] ?? null,
            'tipo' => $row['tipo'] ?? null,
            'resultado' => $row['resultado'] ?? 'pendiente',
            'cantidad_muestra' => $row['cant_muestra'] ?? $row['cantidad_muestra'] ?? 0,
            'cantidad_defectuosa' => $row['cant_defectuosa'] ?? $row['cantidad_defectuosa'] ?? 0,
            'observaciones' => $row['observaciones'] ?? null,
            'fecha' => isset($row['fecha']) ? Carbon::parse($row['fecha']) : now(),
        ]);
    }
}
