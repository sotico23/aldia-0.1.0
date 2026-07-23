<?php

namespace App\Imports;

use App\Models\Nomina;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class NominasImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row): Nomina
    {
        return new Nomina([
            'owner_id' => auth()->user()->getOwnerId(),
            'periodo' => $row['periodo'] ?? null,
            'fecha_inicio' => ! empty($row['fecha_inicio']) ? $row['fecha_inicio'] : null,
            'fecha_fin' => ! empty($row['fecha_fin']) ? $row['fecha_fin'] : null,
            'total_bruto' => is_numeric($row['total_bruto'] ?? null) ? $row['total_bruto'] : 0,
            'total_deducciones' => is_numeric($row['total_deducciones'] ?? null) ? $row['total_deducciones'] : 0,
            'total_neto' => is_numeric($row['total_neto'] ?? null) ? $row['total_neto'] : 0,
            'estado' => $row['estado'] ?? 'borrador',
            'notas' => $row['notas'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'periodo' => 'required|string',
        ];
    }
}
