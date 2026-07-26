<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\MetricResult;
use App\Models\Producto;

/**
 * Single source of truth for weight/volume metric calculations.
 *
 * Accepts a Producto and the sold/delivered quantity, returns
 * a MetricResult with kg and litros computed according to the
 * product's unit of measure, contenido_por_unidad, and peso_base.
 *
 * Rules:
 *  - 'kg' / 'kilo' / 'kilogramo' / 'kilogramos' → weight product
 *      kg = (cantidad × contenido_por_unidad) + (cantidad × peso_base)
 *  - 'lt' / 'litro' / 'litros'                  → liquid product
 *      litros = cantidad × contenido_por_unidad
 *      kg     = cantidad × peso_base   (packaging tare)
 *  - 'unidad' / anything else                    → unit product
 *      kg     = cantidad × peso_base   (if any)
 */
final class CalculateMetricAction
{
    /** Normalize an arbitrary unit string to a canonical bucket. */
    private const UNIT_KG = ['kg', 'kilo', 'kilogramo', 'kilogramos'];

    private const UNIT_LT = ['lt', 'litro', 'litros', 'l'];

    public function execute(Producto $producto, float $cantidad): MetricResult
    {
        if ($cantidad <= 0.0) {
            return MetricResult::zero();
        }

        $contenido = (float) ($producto->contenido_por_unidad ?? 1.0);
        $pesoBase = (float) ($producto->peso_base ?? 0.0);
        $unidad = strtolower(trim((string) ($producto->unidad_medida ?? 'unidad')));

        if (in_array($unidad, self::UNIT_KG, strict: true)) {
            return new MetricResult(
                kg: ($cantidad * $contenido) + ($cantidad * $pesoBase),
                litros: 0.0,
            );
        }

        if (in_array($unidad, self::UNIT_LT, strict: true)) {
            return new MetricResult(
                kg: $cantidad * $pesoBase,
                litros: $cantidad * $contenido,
            );
        }

        // Unit product: only contributes peso_base (tare/packaging weight).
        return new MetricResult(
            kg: $cantidad * $pesoBase,
            litros: 0.0,
        );
    }

    /**
     * Convenience: resolve the Producto by ID, then calculate.
     * Returns MetricResult::zero() if the product does not exist.
     */
    public function executeByProductoId(int $productoId, float $cantidad): MetricResult
    {
        $producto = Producto::find($productoId);

        if (! $producto) {
            return MetricResult::zero();
        }

        return $this->execute($producto, $cantidad);
    }
}
