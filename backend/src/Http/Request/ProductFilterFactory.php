<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Exception\ValidationException;
use App\Repository\ProductFilter;

/** Builds ProductFilter from query parameters of GET /api/products. */
final class ProductFilterFactory
{
    /**
     * @param array<string, mixed> $query
     *
     * @throws ValidationException
     */
    public function fromQuery(array $query): ProductFilter
    {
        $errors = [];

        $page = self::intParam($query, 'page', 1, 1, \PHP_INT_MAX, $errors);
        $limit = self::intParam($query, 'limit', ProductFilter::DEFAULT_LIMIT, 1, ProductFilter::MAX_LIMIT, $errors);
        $priceMin = self::priceParam($query, 'price_min', $errors);
        $priceMax = self::priceParam($query, 'price_max', $errors);
        if (null !== $priceMin && null !== $priceMax && (float) $priceMin > (float) $priceMax) {
            $errors['price_min'] = 'price_min должен быть не больше price_max';
        }

        $name = isset($query['name']) && \is_string($query['name']) ? trim($query['name']) : '';
        if (mb_strlen($name) > 255) {
            $errors['name'] = 'name — не более 255 символов';
        }

        if ([] !== $errors) {
            throw new ValidationException('Некорректные параметры запроса', $errors);
        }

        return new ProductFilter($page, $limit, '' === $name ? null : $name, $priceMin, $priceMax);
    }

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $errors
     */
    private static function intParam(array $query, string $key, int $default, int $min, int $max, array &$errors): int
    {
        if (!isset($query[$key]) || '' === $query[$key]) {
            return $default;
        }
        $value = filter_var($query[$key], \FILTER_VALIDATE_INT);
        if (false === $value || $value < $min || $value > $max) {
            $errors[$key] = \sprintf('%s — целое число от %d до %d', $key, $min, $max);

            return $default;
        }

        return $value;
    }

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $errors
     */
    private static function priceParam(array $query, string $key, array &$errors): ?string
    {
        if (!isset($query[$key]) || '' === $query[$key]) {
            return null;
        }
        $raw = \is_scalar($query[$key]) ? str_replace(',', '.', (string) $query[$key]) : '';
        if (!is_numeric($raw) || (float) $raw < 0) {
            $errors[$key] = \sprintf('%s — неотрицательное число', $key);

            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }
}
