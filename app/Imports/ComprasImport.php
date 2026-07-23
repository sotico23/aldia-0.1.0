<?php

namespace App\Imports;

use App\Models\Compra;
use App\Models\Proveedor;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ComprasImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $ownerId = Auth::user()->getOwnerId();

        // Buscar proveedor por nombre si se proporciona
        $proveedorId = null;
        if (! empty($row['proveedor'])) {
            $proveedor = Proveedor::where('nombre', 'like', "%{$row['proveedor']}%")
                ->where('owner_id', $ownerId)
                ->first();
            $proveedorId = $proveedor?->id;
        }

        return new Compra([
            'owner_id' => $ownerId,
            'numero' => $row['numero'] ?? $row['id'] ?? uniqid(),
            'proveedor_id' => $proveedorId ?? $row['proveedor_id'] ?? null,
            'fecha' => $row['fecha'] ?? now()->toDateString(),
            'subtotal' => $row['subtotal'] ?? 0,
            'iva' => $row['iva'] ?? 0,
            'total' => $row['total'] ?? 0,
            'estado' => $row['estado'] ?? 'pendiente',
            'notas' => $row['notas'] ?? null,
        ]);
    }
}
