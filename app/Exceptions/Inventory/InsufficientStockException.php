<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(string $productName, int $available, int $requested)
    {
        parent::__construct(
            "Stock insuficiente para '{$productName}'. Disponible: {$available}, solicitado: {$requested}."
        );
    }
}