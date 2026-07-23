<?php

namespace App\Imports;

use App\Models\Prospecto;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProspectosImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $ownerId = Auth::user()->getOwnerId();

        return Prospecto::updateOrCreate(
            [
                'email' => $row['email'] ?? null,
                'nombre' => $row['nombre'],
                'owner_id' => $ownerId,
            ],
            [
                'rut' => $row['rut'] ?? null,
                'telefono' => $row['telefono'] ?? null,
                'empresa' => $row['empresa'] ?? null,
                'cargo' => $row['cargo'] ?? null,
                'direccion' => $row['direccion'] ?? null,
                'comuna' => $row['comuna'] ?? null,
                'region' => $row['region'] ?? null,
                'descripcion' => $row['descripcion'] ?? null,
                'fuente' => $row['fuente'] ?? null,
                'estado' => $row['estado'] ?? 'nuevo',
                'prioridad' => $row['prioridad'] ?? 'media',
                'valor_estimado' => $row['valor_estimado'] ?? 0,
                'notas' => $row['notas'] ?? null,
            ]
        );
    }
}
