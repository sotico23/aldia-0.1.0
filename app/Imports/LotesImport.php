<?php

namespace App\Imports;

use App\Models\Lote;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LotesImport implements ToModel, WithHeadingRow
{
    protected $ownerId;

    public function __construct($ownerId = null)
    {
        $this->ownerId = $ownerId ?: Auth::user()->getOwnerId();
    }

    public function model(array $row)
    {
        if (empty($row['numero_lote'])) {
            return null;
        }

        return Lote::updateOrCreate(
            [
                'owner_id' => $this->ownerId,
                'numero_lote' => $row['numero_lote'],
            ],
            [
                'producto' => $row['producto'] ?? null,
                'cantidad' => (int) ($row['cantidad'] ?? 0),
                'fecha_vencimiento' => ! empty($row['fecha_vencimiento']) ? $row['fecha_vencimiento'] : null,
                'estado' => $row['estado'] ?? 'disponible',
                'notas' => $row['notas'] ?? null,
            ]
        );
    }
}
