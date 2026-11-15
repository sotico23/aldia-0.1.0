<?php

namespace App\Services;

use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Scopes\OwnerScope;
use Illuminate\Support\Facades\DB;

class EntregaPedidoService
{
    public function sync(Entrega $entrega): Pedido
    {
        return DB::transaction(function () use ($entrega): Pedido {
            $entrega = Entrega::withoutGlobalScope(OwnerScope::class)
                ->where('owner_id', $entrega->owner_id)
                ->lockForUpdate()->findOrFail($entrega->id);
            $venta = $entrega->venta_id ? $entrega->venta()->where('owner_id', $entrega->owner_id)->first() : null;
            $pedido = $entrega->pedido_id ? $entrega->pedido()->where('owner_id', $entrega->owner_id)->first() : null;

            if (! $pedido && $venta) {
                $numeroPedido = str_starts_with($venta->numero, 'PEDIDO-')
                    ? substr($venta->numero, 7)
                    : 'POS-DEL-'.$venta->id;
                $pedido = Pedido::withoutGlobalScope(OwnerScope::class)
                    ->where('owner_id', $entrega->owner_id)
                    ->where('numero_pedido', $numeroPedido)->first();
            }

            $pedido ??= new Pedido([
                'owner_id' => $entrega->owner_id,
                'user_id' => $venta?->user_id ?? $entrega->owner_id,
                'numero_pedido' => Pedido::generarNumeroPedido(),
                'dispatch_mode' => Pedido::DISPATCH_INTERNAL,
                'subtotal' => $venta?->subtotal ?? 0,
                'impuesto' => $venta?->iva ?? 0,
                'total' => $venta?->total ?? 0,
                'costo_envio' => $venta?->delivery_cost ?? 0,
                'metodo_pago' => $venta?->metodo_pago,
            ]);

            $repartidor = $entrega->repartidor_id
                ? Repartidor::withoutGlobalScope(OwnerScope::class)
                    ->where('owner_id', $entrega->owner_id)->find($entrega->repartidor_id)
                : null;
            $libre = $entrega->estado_asignacion === 'libre'
                && $entrega->repartidor_id === null && $entrega->conductor_id === null;
            $estado = match ($entrega->estado) {
                'en_ruta' => 'enviado',
                'entregado' => 'entregado',
                'cancelado' => 'cancelado',
                default => 'preparando',
            };

            $pedido->fill([
                'nombre_cliente' => $entrega->getRawOriginal('cliente'),
                'telefono_cliente' => $entrega->telefono ?? $venta?->delivery_phone,
                'direccion_cliente' => $entrega->direccion,
                'notas' => $entrega->notas,
                'estado' => $estado,
                'repartidor_id' => $libre ? null : $repartidor?->user_id,
                'pool_bloqueado' => ! $libre && ! $repartidor,
            ]);

            if ($libre) {
                $pedido->fill([
                    'hora_aceptado' => null,
                    'hora_recogido' => null,
                    'hora_entregado' => null,
                    'pool_visible_at' => null,
                    'pool_entrada_at' => $pedido->pool_entrada_at ?? now(),
                ]);
            } elseif ($repartidor) {
                $pedido->hora_aceptado ??= now();
            }

            $pedido->save();
            $entrega->forceFill(['pedido_id' => $pedido->id])->saveQuietly();

            if (! $pedido->items()->exists()) {
                foreach ($entrega->items()->with('producto')->get() as $item) {
                    if (! $item->producto) {
                        continue;
                    }

                    $precio = (float) $item->producto->precio_venta;
                    $pedido->items()->create([
                        'owner_id' => $entrega->owner_id,
                        'producto_id' => $item->producto_id,
                        'nombre_producto' => $item->producto->nombre,
                        'precio_unitario' => $precio,
                        'cantidad' => $item->cantidad_pedida,
                        'subtotal' => $precio * (float) $item->cantidad_pedida,
                    ]);
                }
            }

            return $pedido;
        });
    }
}
