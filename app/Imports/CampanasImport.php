<?php

namespace App\Imports;

use App\Models\Campana;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CampanasImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $nombre = $row['nombre'] ?? null;
        if (! $nombre) {
            return null;
        }

        return new Campana([
            'nombre' => $nombre,
            'descripcion' => $row['descripcion'] ?? null,
            'tipo' => $row['tipo'] ?? null,
            'canal' => $row['canal'] ?? null,
            'objetivo' => $row['objetivo'] ?? null,
            'fecha_inicio' => $row['fecha_inicio'] ?? null,
            'fecha_fin' => $row['fecha_fin'] ?? null,
            'presupuesto' => isset($row['presupuesto']) ? (float) $row['presupuesto'] : null,
            'presupuesto_real' => isset($row['presupuesto_real']) ? (float) $row['presupuesto_real'] : null,
            'visitas' => isset($row['visitas']) ? (int) $row['visitas'] : null,
            'leads' => isset($row['leads']) ? (int) $row['leads'] : null,
            'conversiones' => isset($row['conversiones']) ? (int) $row['conversiones'] : null,
            'roi' => isset($row['roi']) ? (float) $row['roi'] : null,
            'estado' => $row['estado'] ?? 'activa',
        ]);
    }
}
