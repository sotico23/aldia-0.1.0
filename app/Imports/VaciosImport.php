<?php

namespace App\Imports;

use App\Models\Producto;
use App\Models\Vacio;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VaciosImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $ownerId = Auth::user()->getOwnerId();

        $productoId = null;
        if (! empty($row['producto'])) {
            $producto = Producto::where('nombre', 'like', "%{$row['producto']}%")
                ->where('owner_id', $ownerId)
                ->first();
            $productoId = $producto?->id;
        }

        return Vacio::updateOrCreate(
            [
                'producto_id' => $productoId ?? $row['producto_id'] ?? null,
                'owner_id' => $ownerId,
            ],
            [
                'cantidad' => $row['cantidad'] ?? 0,
                'cantidad_minima' => $row['cantidad_minima'] ?? 0,
                'estado' => $row['estado'] ?? 'disponible',
                'ubicacion' => $row['ubicacion'] ?? null,
                'observaciones' => $row['observaciones'] ?? null,
            ]
        );
    }
}
