<?php

namespace App\Imports;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InventariosImport implements ToModel, WithHeadingRow
{
    protected $ownerId;

    public function __construct($ownerId = null)
    {
        $this->ownerId = $ownerId ?: Auth::user()->getOwnerId();
    }

    public function model(array $row)
    {
        $productoNombre = $row['producto'] ?? null;
        if (! $productoNombre) {
            return null;
        }

        $producto = Producto::where('nombre', 'like', '%'.$productoNombre.'%')
            ->where('owner_id', $this->ownerId)
            ->first();

        if (! $producto) {
            return null;
        }

        $almacen = null;
        if (! empty($row['almacen'])) {
            $almacen = Almacen::where('nombre', 'like', '%'.$row['almacen'].'%')
                ->where('owner_id', $this->ownerId)
                ->first();
        }

        if (! $almacen) {
            $almacen = Almacen::where('owner_id', $this->ownerId)->first();
        }

        if (! $almacen) {
            return null;
        }

        DB::transaction(function () use ($row, $producto, $almacen) {
            Inventario::updateOrCreate(
                [
                    'producto_id' => $producto->id,
                    'almacen_id' => $almacen->id,
                ],
                [
                    'cantidad' => (int) ($row['cantidad'] ?? 0),
                    'cantidad_minima' => (int) ($row['cantidad_minima'] ?? 0),
                    'ubicacion' => $row['ubicacion'] ?? null,
                    'owner_id' => $this->ownerId,
                ]
            );
        });

        return null;
    }
}
