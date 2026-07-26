<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Holds the result of a weight/volume metric calculation.
 *
 * @property-read float $kg           Total weight in kilograms.
 * @property-read float $litros       Total volume in liters.
 * @property-read float $subtotal     Unified metric value: kg if weightable, litros if liquid.
 */
final class MetricResult
{
    public function __construct(
        public readonly float $kg,
        public readonly float $litros,
    ) {}

    /**
     * The primary metric value persisted as subtotal_metrica.
     * Kilograms take precedence; if zero, returns litros.
     */
    public function subtotal(): float
    {
        return $this->kg > 0.0 ? $this->kg : $this->litros;
    }

    /**
     * Returns a zero-value instance (no metric).
     */
    public static function zero(): self
    {
        return new self(kg: 0.0, litros: 0.0);
    }

    /**
     * Merge two results together.
     */
    public function add(self $other): self
    {
        return new self(
            kg: $this->kg + $other->kg,
            litros: $this->litros + $other->litros,
        );
    }

    /**
     * Convert to a plain array for serialization/response.
     *
     * @return array{kg: float, litros: float, subtotal: float}
     */
    public function toArray(): array
    {
        return [
            'kg' => $this->kg,
            'litros' => $this->litros,
            'subtotal' => $this->subtotal(),
        ];
    }
}
