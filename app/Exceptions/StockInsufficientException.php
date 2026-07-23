<?php

namespace App\Exceptions;

use RuntimeException;

class StockInsufficientException extends RuntimeException
{
    public function __construct(
        string $productName,
        float $requested,
        float $available,
        ?int $productId = null,
    ) {
        parent::__construct(
            "Stock insuficiente para \"{$productName}\". ".
            "Solicitado: {$requested}, disponible: {$available}."
        );

        $this->code = 422;
    }
}
