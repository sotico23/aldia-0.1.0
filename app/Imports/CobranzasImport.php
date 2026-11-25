<?php

namespace App\Imports;

use App\Models\Cobranza;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CobranzasImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $ownerId = Auth::user()->getOwnerId();

        return Cobranza::updateOrCreate(
            [
                'owner_id' => $ownerId,
                'cliente' => $row['cliente'] ?? null,
                'factura' => $row['factura'] ?? null,
            ],
            [
                'monto' => isset($row['monto']) ? (float) $row['monto'] : 0.0,
                'fecha_pago' => ! empty($row['fecha_pago']) ? $row['fecha_pago'] : null,
                'metodo' => $row['metodo'] ?? null,
                'estado' => $row['estado'] ?? 'pendiente',
                'notas' => $row['notas'] ?? null,
            ]
        );
    }
}
