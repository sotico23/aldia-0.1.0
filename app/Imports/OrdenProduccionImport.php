<?php

namespace App\Imports;

use App\Models\OrdenProduccion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class OrdenProduccionImport implements ToModel, WithHeadingRow
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        $numero = $row['numero'] ?? null;
        if (! $numero) {
            return null;
        }

        return new OrdenProduccion([
            'numero' => $numero,
            'producto' => $row['producto'] ?? null,
            'cantidad' => $row['cantidad'] ?? null,
            'fecha_inicio' => isset($row['fecha_inicio']) ? Carbon::parse($row['fecha_inicio']) : null,
            'fecha_fin' => isset($row['fecha_fin']) ? Carbon::parse($row['fecha_fin']) : null,
            'progreso' => $row['progreso_'] ?? $row['progreso'] ?? 0,
            'estado' => $row['estado'] ?? 'pendiente',
            'notas' => $row['notas'] ?? null,
        ]);
    }
}
