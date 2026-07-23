<?php

namespace App\Imports;

use App\Models\Vehiculo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VehiculosImport implements ToCollection, WithHeadingRow
{
    protected $ownerId;

    public function __construct($ownerId = null)
    {
        $this->ownerId = $ownerId ?: Auth::user()->getOwnerId();
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $placa = $row['placa'] ?? null;

            if (! $placa && ! $id) {
                continue;
            }

            $placa = strtoupper(trim($placa));

            // Map variables, taking care of potential accent/space variations in keys
            $imei = $row['imei'] ?? null;
            $marca = $row['marca'] ?? null;
            $modelo = $row['modelo'] ?? null;
            $tipo = $row['tipo'] ?? null;
            $ano = $row['ano'] ?? $row['año'] ?? $row['ano'] ?? null;
            $color = $row['color'] ?? null;
            $kilometraje = isset($row['kilometraje']) && is_numeric($row['kilometraje']) ? (float) $row['kilometraje'] : 0;
            $estado = $row['estado'] ?? 'disponible';
            $notas = $row['notas'] ?? null;

            $match = [];
            if ($id) {
                $match = ['id' => $id, 'owner_id' => $this->ownerId];
            } else {
                $match = ['placa' => $placa, 'owner_id' => $this->ownerId];
            }

            Vehiculo::updateOrCreate(
                $match,
                [
                    'owner_id' => $this->ownerId,
                    'placa' => $placa ?? ($id ? Vehiculo::find($id)?->placa : ''),
                    'imei' => $imei,
                    'marca' => $marca,
                    'modelo' => $modelo,
                    'tipo' => $tipo,
                    'año' => $ano,
                    'color' => $color,
                    'kilometraje' => $kilometraje,
                    'estado' => $estado,
                    'notas' => $notas,
                ]
            );
        }
    }
}
