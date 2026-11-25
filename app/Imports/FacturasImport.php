<?php

namespace App\Imports;

use App\Models\Factura;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FacturasImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $ownerId = Auth::user()->getOwnerId();

        return Factura::updateOrCreate(
            [
                'owner_id' => $ownerId,
                'numero' => $row['numero'] ?? null,
            ],
            [
                'cliente_id' => $row['cliente_id'] ?? null,
                'fecha' => ! empty($row['fecha']) ? $row['fecha'] : null,
                'fecha_vencimiento' => ! empty($row['fecha_vencimiento']) ? $row['fecha_vencimiento'] : null,
                'subtotal' => isset($row['subtotal']) ? (float) $row['subtotal'] : 0.0,
                'impuesto' => isset($row['impuesto']) ? (float) $row['impuesto'] : 0.0,
                'total' => isset($row['total']) ? (float) $row['total'] : 0.0,
                'tipo' => $row['tipo'] ?? 'venta',
                'estado' => $row['estado'] ?? 'pendiente',
                'notas' => $row['notas'] ?? null,
            ]
        );
    }
}
