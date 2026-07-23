<?php

namespace App\Imports;

use App\Models\Cliente;
use App\Models\Oportunidad;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class OportunidadesImport implements ToModel, WithHeadingRow
{
    protected $ownerId;

    public function __construct($ownerId = null)
    {
        $this->ownerId = $ownerId ?: Auth::user()->getOwnerId();
    }

    public function model(array $row)
    {
        $nombre = $row['nombre'] ?? null;
        if (! $nombre) {
            return null;
        }

        $clienteId = null;
        if (! empty($row['cliente'])) {
            $cliente = Cliente::where('nombre', 'like', '%'.$row['cliente'].'%')
                ->where('owner_id', $this->ownerId)
                ->first();
            $clienteId = $cliente?->id;
        }

        $etapa = $row['etapa'] ?? 'prospecting';
        $etapasValidas = ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
        if (! in_array($etapa, $etapasValidas)) {
            $etapa = 'prospecting';
        }

        DB::transaction(function () use ($row, $nombre, $clienteId, $etapa) {
            Oportunidad::updateOrCreate(
                [
                    'nombre' => $nombre,
                    'owner_id' => $this->ownerId,
                ],
                [
                    'cliente_id' => $clienteId,
                    'valor' => (float) ($row['valor'] ?? 0),
                    'etapa' => $etapa,
                    'probabilidad' => (int) ($row['probabilidad'] ?? 10),
                    'fecha_cierre_estimada' => $row['fecha_cierre_estimada'] ?? null,
                    'descripcion' => $row['descripcion'] ?? '',
                ]
            );
        });

        return null;
    }
}
