<?php

namespace App\Imports;

use App\Models\Timesheet;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TimesheetsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Timesheet([
            'fecha' => $row['fecha'] ?? null,
            'horas' => $row['horas'] ?? 0,
            'descripcion' => $row['descripcion'] ?? null,
            'tipo' => $row['tipo'] ?? 'desarrollo',
            'estado' => $row['estado'] ?? 'pendiente',
        ]);
    }
}
