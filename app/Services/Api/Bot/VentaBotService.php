<?php

namespace App\Services\Api\Bot;

use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Venta;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VentaBotService
{
    private const MAX_LIMIT = 100;

    public function paginate(int $ownerId, array $filters): array
    {
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), self::MAX_LIMIT);
        $offset = max((int) ($filters['offset'] ?? 0), 0);

        $query = Venta::where('owner_id', $ownerId)->with('cliente');

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q
                ->where('numero', 'like', $term)
                ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', $term)));
        }

        if (! empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (! empty($filters['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $filters['fecha_desde']);
        }

        if (! empty($filters['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $filters['fecha_hasta']);
        }

        if (! empty($filters['cliente_id'])) {
            $query->where('cliente_id', (int) $filters['cliente_id']);
        }

        $total = (clone $query)->count();

        $items = $query->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function find(int $ownerId, int $id): ?Venta
    {
        return Venta::with('cliente', 'detalleVentas.producto')
            ->where('owner_id', $ownerId)
            ->find($id);
    }

    public function create(int $ownerId, int $actingUserId, array $data): Venta
    {
        return DB::transaction(function () use ($ownerId, $actingUserId, $data): Venta {
            $detalles = $data['detalles'] ?? [];

            $subtotal = collect($detalles)->sum(fn (array $d): float => (float) $d['cantidad'] * (float) $d['precio_unitario']);
            $iva = round($subtotal * (float) (config('taxes.iva_rate') ?? 0.19), 2);
            $total = round($subtotal + $iva, 2);

            $venta = Venta::create([
                'owner_id' => $ownerId,
                'user_id' => $actingUserId,
                'cliente_id' => $data['cliente_id'] ?? null,
                'fecha' => $data['fecha'] ?? now()->toDateString(),
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total,
                'metodo_pago' => $data['metodo_pago'] ?? 'efectivo',
                'tipo_documento' => $data['tipo_documento'] ?? 'boleta',
                'es_pos' => $data['es_pos'] ?? false,
                'estado' => $data['estado'] ?? 'pendiente',
                'notas' => $data['notas'] ?? null,
                'currency' => $data['currency'] ?? 'CLP',
                'numero' => $data['numero'] ?? $this->generateNumero(),
            ]);

            foreach ($detalles as $detalle) {
                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'owner_id' => $ownerId,
                    'producto_id' => $detalle['producto_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal' => (float) $detalle['cantidad'] * (float) $detalle['precio_unitario'],
                ]);

                $this->decrementarStock($ownerId, (int) $detalle['producto_id'], (float) $detalle['cantidad']);
            }

            return $venta->load('cliente', 'detalleVentas.producto');
        });
    }

    public function update(Venta $venta, array $data): Venta
    {
        $venta->update(Arr::only($data, ['estado', 'notas', 'metodo_pago', 'fecha', 'tipo_documento']));

        return $venta->load('cliente', 'detalleVentas.producto');
    }

    public function cancel(Venta $venta): Venta
    {
        $venta->update(['estado' => 'cancelada']);

        return $venta->fresh();
    }

    protected function generateNumero(): string
    {
        do {
            $numero = 'FV-'.Str::upper(Str::random(8));
        } while (Venta::where('numero', $numero)->exists());

        return $numero;
    }

    protected function decrementarStock(int $ownerId, int $productoId, float $cantidad): void
    {
        $inventario = Inventario::where('owner_id', $ownerId)
            ->where('producto_id', $productoId)
            ->first();

        if ($inventario) {
            $inventario->decrement('cantidad', $cantidad);
        }
    }

    public function serialize(Venta $venta): array
    {
        return [
            'id' => $venta->id,
            'numero' => $venta->numero,
            'fecha' => $venta->fecha?->toDateString(),
            'cliente_id' => $venta->cliente_id,
            'cliente_nombre' => $venta->cliente?->nombre,
            'subtotal' => (float) $venta->subtotal,
            'iva' => (float) $venta->iva,
            'descuento' => (float) ($venta->descuento ?? 0),
            'total' => (float) $venta->total,
            'metodo_pago' => $venta->metodo_pago,
            'tipo_documento' => $venta->tipo_documento,
            'estado' => $venta->estado,
            'es_pos' => (bool) $venta->es_pos,
            'notas' => $venta->notas,
        ];
    }

    public function serializeDetail(Venta $venta): array
    {
        return $this->serialize($venta) + [
            'items' => $venta->detalleVentas->map(fn (DetalleVenta $detalle): array => [
                'producto_id' => $detalle->producto_id,
                'producto' => $detalle->producto?->nombre,
                'cantidad' => (float) $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'subtotal' => (float) $detalle->subtotal,
            ])->values(),
        ];
    }
}
