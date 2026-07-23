<?php

namespace App\Imports;

use App\Models\Cliente;
use App\Models\Cotizacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CotizacionesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        $grouped = $rows->groupBy('numero');
        $ownerId = auth()->user()->getOwnerId();

        DB::transaction(function () use ($grouped, $ownerId) {
            foreach ($grouped as $numero => $items) {
                if (empty($numero)) {
                    continue;
                }

                $first = $items->first();
                $clienteEmail = $first['cliente_email'] ?? null;
                if (! $clienteEmail) {
                    continue;
                }

                $cliente = Cliente::where(fn ($q) => $q->where('email', $clienteEmail))
                    ->where(fn ($q) => $q->where('owner_id', $ownerId))
                    ->first();

                if (! $cliente) {
                    continue;
                }

                $detalles = [];
                $subtotal = 0;
                foreach ($items as $item) {
                    if (! empty($item['item_descripcion'])) {
                        $cantidad = (int) ($item['item_cantidad'] ?? 0);
                        $precio = round($item['item_precio'] ?? 0);
                        $detalles[] = [
                            'descripcion' => $item['item_descripcion'],
                            'cantidad' => $cantidad,
                            'precio' => $precio,
                        ];
                        $subtotal += round($cantidad * $precio);
                    }
                }

                $impuesto = round($subtotal * config('taxes.iva_rate'));
                $total = $subtotal + $impuesto;

                Cotizacion::updateOrCreate(
                    ['numero' => $numero, 'owner_id' => $ownerId],
                    [
                        'cliente_id' => $cliente->id,
                        'user_id' => auth()->id(),
                        'fecha' => (isset($first['fecha']) && ! empty($first['fecha'])) ? $first['fecha'] : now(),
                        'fecha_validez' => ! empty($first['vence']) ? $first['vence'] : null,
                        'estado' => $first['estado'] ?: 'borrador',
                        'condiciones' => $first['condiciones'] ?? '',
                        'notas' => $first['notas'] ?? '',
                        'detalles' => $detalles,
                        'subtotal' => (int) $subtotal,
                        'impuesto' => (int) $impuesto,
                        'total' => (int) $total,
                    ]
                );
            }
        });
    }
}
