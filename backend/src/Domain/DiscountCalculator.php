<?php

declare(strict_types=1);

namespace App\Domain;

final class DiscountCalculator
{
    /** Discount in % = (price − purchase price) / price × 100, rounded to 2 decimals. */
    public static function percent(float $price, float $purchasePrice): string
    {
        if ($price <= 0.0) {
            throw new \InvalidArgumentException('Price must be positive');
        }

        return number_format(round(($price - $purchasePrice) / $price * 100, 2), 2, '.', '');
    }
}
