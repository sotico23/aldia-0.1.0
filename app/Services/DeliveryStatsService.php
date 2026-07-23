<?php

namespace App\Services;

use App\Models\EntregaItem;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class DeliveryStatsService
{
    /**
     * Get accumulated stats for a driver in a date range.
     */
    public function getDriverStats(int $ownerId, ?int $conductorId = null, $startDate = null, $endDate = null): array
    {
        $query = EntregaItem::where('owner_id', $ownerId);

        if ($conductorId) {
            $query->whereHas('entrega', function ($q) use ($conductorId) {
                $q->where('conductor_id', $conductorId);
            });
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $stats = $query->select(
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(unidad_medida) IN ('kg','kilo','kilogramo','kilogramos') THEN subtotal_metrica ELSE 0 END), 0) as kg"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(unidad_medida) IN ('lt','litro','litros') THEN subtotal_metrica ELSE 0 END), 0) as litros"),
            DB::raw('COALESCE(SUM(unidades_totales), 0) as unidades')
        )->first();

        return [
            'kg' => (float) ($stats->kg ?? 0),
            'litros' => (float) ($stats->litros ?? 0),
            'unidades' => (int) ($stats->unidades ?? 0),
        ];
    }

    /**
     * Calculate items totals based on product attributes.
     */
    public function calculateItemTotals(int $productoId, float $cantidad): array
    {
        $producto = Producto::find($productoId);
        if (! $producto) {
            return ['kg' => 0, 'litros' => 0, 'unidades' => 0];
        }

        $kg = 0;
        $litros = 0;
        $unidades = (int) $cantidad;

        $unidad = strtolower($producto->unidad_medida ?? 'unidad');
        $contenido = (float) ($producto->contenido_por_unidad ?? 1.0);
        $pesoBase = (float) ($producto->peso_base ?? 0.0);

        if ($unidad === 'kg' || $unidad === 'kilo') {
            $kg = ($cantidad * $contenido) + ($cantidad * $pesoBase);
        } elseif ($unidad === 'lt' || $unidad === 'litro') {
            $litros = ($cantidad * $contenido);
            // Even if it's liquid, it might have a base weight (packaging)
            $kg = ($cantidad * $pesoBase);
        } else {
            // For 'unidad' type, we only use peso_base if it exists
            $kg = ($cantidad * $pesoBase);
        }

        return [
            'kg' => $kg,
            'litros' => $litros,
            'unidades' => $unidades,
        ];
    }
}
