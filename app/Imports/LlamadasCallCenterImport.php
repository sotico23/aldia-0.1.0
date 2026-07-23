<?php

namespace App\Imports;

use App\Models\Cliente;
use App\Models\LlamadaCallCenter;
use App\Models\Prospecto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LlamadasCallCenterImport implements ToModel, WithHeadingRow
{
    protected $ownerId;

    public function __construct($ownerId = null)
    {
        $this->ownerId = $ownerId ?: Auth::user()->getOwnerId();
    }

    public function model(array $row)
    {
        $tipo = $row['tipo'] ?? null;
        if (! $tipo) {
            return null;
        }

        $clienteId = null;
        if (! empty($row['cliente'])) {
            $cliente = Cliente::where('nombre', 'like', '%'.$row['cliente'].'%')
                ->where('owner_id', $this->ownerId)
                ->first();
            $clienteId = $cliente?->id;
        }

        $prospectoId = null;
        if (! empty($row['prospecto'])) {
            $prospecto = Prospecto::where('nombre', 'like', '%'.$row['prospecto'].'%')
                ->where('owner_id', $this->ownerId)
                ->first();
            $prospectoId = $prospecto?->id;
        }

        $estadosValidos = ['completada', 'perdida', 'ocupado', 'no_contesta', 'equivocado'];
        $estado = $row['estado'] ?? 'completada';
        if (! in_array($estado, $estadosValidos)) {
            $estado = 'completada';
        }

        $tiposValidos = ['entrante', 'saliente'];
        if (! in_array($tipo, $tiposValidos)) {
            $tipo = 'entrante';
        }

        DB::transaction(function () use ($row, $clienteId, $prospectoId, $estado, $tipo) {
            LlamadaCallCenter::updateOrCreate(
                [
                    'tipo' => $tipo,
                    'numero_telefono' => $row['numero_telefono'] ?? null,
                    'fecha' => $row['fecha'] ?? now()->toDateTimeString(),
                    'owner_id' => $this->ownerId,
                ],
                [
                    'user_id' => Auth::id(),
                    'cliente_id' => $clienteId,
                    'prospecto_id' => $prospectoId,
                    'numero_emisor' => $row['numero_emisor'] ?? null,
                    'numero_remitente' => $row['numero_remitente'] ?? null,
                    'estado' => $estado,
                    'duracion' => (int) ($row['duracion'] ?? 0),
                    'notas' => $row['notas'] ?? '',
                ]
            );
        });

        return null;
    }
}
