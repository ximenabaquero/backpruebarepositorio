<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

class EquipoHasNoStockException extends RuntimeException
{
    public function __construct(string $productName)
    {
        parent::__construct(
            "'{$productName}' es un equipo y no tiene stock ni puede registrarse como consumo."
        );
    }
}