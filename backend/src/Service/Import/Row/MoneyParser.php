<?php

declare(strict_types=1);

namespace App\Service\Import\Row;

final class MoneyParser
{
    private const MAX_AMOUNT = 9_999_999_999.99;

    /** Parses "1 320,50" / "1320.50" / "1320" into float; null when it is not a valid amount. */
    public static function parse(string $raw): ?float
    {
        $normalized = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim($raw));
        if ('' === $normalized || !preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            return null;
        }
        $value = (float) $normalized;

        return $value > self::MAX_AMOUNT ? null : $value;
    }

    public static function format(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
