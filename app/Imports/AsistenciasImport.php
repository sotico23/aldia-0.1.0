<?php

namespace App\Imports;

use App\Models\Asistencia;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class AsistenciasImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row): Asistencia
    {
        return new Asistencia([
            'owner_id' => auth()->user()->getOwnerId(),
            'empleado_id' => $row['empleado_id'] ?? null,
            'fecha' => ! empty($row['fecha']) ? $row['fecha'] : null,
            'hora_entrada' => $row['hora_entrada'] ?? null,
            'hora_salida' => $row['hora_salida'] ?? null,
            'horas_trabajadas' => is_numeric($row['horas_trabajadas'] ?? null) ? $row['horas_trabajadas'] : null,
            'estado' => $row['estado'] ?? 'presente',
            'notas' => $row['notas'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'fecha' => 'required',
            'estado' => 'nullable|string',
        ];
    }
}
