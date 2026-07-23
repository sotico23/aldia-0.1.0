<?php

namespace App\Imports;

use App\Models\Planificacion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PlanificacionImport implements ToModel, WithHeadingRow
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        $titulo = $row['titulo'] ?? null;
        if (! $titulo) {
            return null;
        }

        return new Planificacion([
            'titulo' => $titulo,
            'descripcion' => $row['descripcion'] ?? null,
            'fecha_inicio' => isset($row['fecha_inicio']) ? Carbon::parse($row['fecha_inicio']) : null,
            'fecha_fin' => isset($row['fecha_fin']) ? Carbon::parse($row['fecha_fin']) : null,
            'estado' => $row['estado'] ?? 'pendiente',
            'prioridad' => $row['prioridad'] ?? 'media',
            'ubicacion' => $row['ubicacion'] ?? null,
            'presupuesto' => $row['presupuesto'] ?? 0,
            'categoria' => $row['categoria'] ?? null,
            'objetivo' => $row['objetivo'] ?? null,
        ]);
    }
}
