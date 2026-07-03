<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service\Ecommerce;

trait RoundsMonetaryValues
{
    private function round(float $value): float
    {
        return round($value, 2);
    }
}
