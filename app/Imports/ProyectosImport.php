<?php

namespace App\Imports;

use App\Models\Proyecto;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProyectosImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Proyecto([
            'nombre' => $row['nombre'] ?? null,
            'descripcion' => $row['descripcion'] ?? null,
            'cliente' => $row['cliente'] ?? null,
            'responsable' => $row['responsable'] ?? null,
            'fecha_inicio' => $row['fecha_inicio'] ?? null,
            'fecha_fin' => $row['fecha_fin'] ?? null,
            'presupuesto' => $row['presupuesto'] ?? 0,
            'gasto_real' => $row['gasto_real'] ?? 0,
            'progreso' => $row['progreso'] ?? 0,
            'estado' => $row['estado'] ?? 'en_progreso',
            'notas' => $row['notas'] ?? null,
        ]);
    }
}
