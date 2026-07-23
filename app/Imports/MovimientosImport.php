<?php

namespace App\Imports;

use App\Models\Movimiento;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MovimientosImport implements ToModel, WithHeadingRow
{
    protected $ownerId;

    public function __construct($ownerId = null)
    {
        $this->ownerId = $ownerId ?: Auth::user()->getOwnerId();
    }

    public function model(array $row)
    {
        if (empty($row['producto']) && empty($row['tipo'])) {
            return null;
        }

        return new Movimiento([
            'owner_id' => $this->ownerId,
            'producto' => $row['producto'] ?? null,
            'tipo' => $row['tipo'] ?? 'entrada',
            'cantidad' => (int) ($row['cantidad'] ?? 0),
            'almacen_origen' => $row['origen'] ?? null,
            'almacen_destino' => $row['destino'] ?? null,
            'referencia' => $row['referencia'] ?? null,
            'notas' => $row['notas'] ?? null,
        ]);
    }
}
