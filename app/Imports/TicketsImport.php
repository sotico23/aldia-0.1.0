<?php

namespace App\Imports;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Ticket;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TicketsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $titulo = $row['titulo'] ?? null;
        if (! $titulo) {
            return null;
        }

        $clienteId = null;
        if (! empty($row['cliente'])) {
            $cliente = Cliente::where('nombre', 'like', '%'.$row['cliente'].'%')->first();
            $clienteId = $cliente?->id;
        }

        $productoId = null;
        if (! empty($row['producto'])) {
            $producto = Producto::where('nombre', 'like', '%'.$row['producto'].'%')->first();
            $productoId = $producto?->id;
        }

        return new Ticket([
            'titulo' => $titulo,
            'descripcion' => $row['descripcion'] ?? null,
            'cliente_id' => $clienteId,
            'producto_id' => $productoId,
            'prioridad' => $row['prioridad'] ?? 'media',
            'estado' => $row['estado'] ?? 'abierto',
            'categoria' => $row['categoria'] ?? null,
            'asignado_a' => $row['asignado_a'] ?? null,
        ]);
    }
}
