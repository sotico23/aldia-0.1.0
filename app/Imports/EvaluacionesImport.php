<?php

namespace App\Imports;

use App\Models\Evaluacion;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class EvaluacionesImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row): Evaluacion
    {
        return new Evaluacion([
            'owner_id' => auth()->user()->getOwnerId(),
            'empleado_id' => $row['empleado_id'] ?? null,
            'evaluador_id' => $row['evaluador_id'] ?? null,
            'fecha' => ! empty($row['fecha']) ? $row['fecha'] : null,
            'periodo' => $row['periodo'] ?? null,
            'puntuacion' => is_numeric($row['puntuacion'] ?? null) ? $row['puntuacion'] : null,
            'tipo' => $row['tipo'] ?? 'desempeno',
            'estado' => $row['estado'] ?? 'pendiente',
            'comentarios' => $row['comentarios'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'estado' => 'nullable|string',
        ];
    }
}
