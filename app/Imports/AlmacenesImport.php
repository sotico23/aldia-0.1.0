<?php

namespace App\Imports;

use App\Models\Almacen;
use App\Models\Empleado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AlmacenesImport implements ToCollection, WithHeadingRow
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
            $codigo = $row['codigo'] ?? null;

            if (! $codigo && ! $id) {
                continue;
            }

            $match = [];
            if ($id) {
                $match = ['id' => $id, 'owner_id' => $this->ownerId];
            } else {
                $match = ['codigo' => $codigo, 'owner_id' => $this->ownerId];
            }

            $almacen = Almacen::updateOrCreate(
                $match,
                [
                    'codigo' => $codigo ?? ($id ? Almacen::find($id)?->codigo : null),
                    'nombre' => $row['nombre'] ?? 'Sin Nombre',
                    'direccion' => $row['direccion'] ?? null,
                    'telefono' => $row['telefono'] ?? null,
                    'capacidad' => isset($row['capacidad']) ? (int) $row['capacidad'] : null,
                    'tipo' => $row['tipo'] ?? 'principal',
                    'activo' => strtolower($row['activo'] ?? 'si') !== 'no',
                    'notas' => $row['notas'] ?? null,
                    'user_id' => Auth::id(),
                    'owner_id' => $this->ownerId,
                    'responsable' => $row['responsable'] ?? null,
                ]
            );

            if (! empty($row['responsable'])) {
                $partes = explode(' ', $row['responsable']);
                $nombre = $partes[0] ?? '';
                $empleado = Empleado::where('nombre', 'like', '%'.$nombre.'%')
                    ->where('owner_id', $this->ownerId)
                    ->first();
                if ($empleado) {
                    $empleado->update(['almacen_id' => $almacen->id]);
                }
            }
        }
    }
}
