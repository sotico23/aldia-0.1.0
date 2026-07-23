<?php

namespace App\Imports;

use App\Models\Reclutamiento;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ReclutamientosImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row): Reclutamiento
    {
        return new Reclutamiento([
            'owner_id' => auth()->user()->getOwnerId(),
            'puesto' => $row['puesto'] ?? '',
            'candidato_id' => $row['candidato_id'] ?? null,
            'fecha_postulacion' => ! empty($row['fecha_postulacion']) ? $row['fecha_postulacion'] : null,
            'fecha_entrevista' => ! empty($row['fecha_entrevista']) ? $row['fecha_entrevista'] : null,
            'estado' => $row['estado'] ?? 'pendiente',
            'resultado' => $row['resultado'] ?? null,
            'notas' => $row['notas'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'puesto' => 'required|string',
        ];
    }
}
