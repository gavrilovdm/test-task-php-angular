<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\ValidationException;

/** Validated query parameters of GET /api/products. */
final readonly class ProductFilter
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 100;

    public function __construct(
        public int $page = 1,
        public int $limit = self::DEFAULT_LIMIT,
        public ?string $name = null,
        public ?string $priceMin = null,
        public ?string $priceMax = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     *
     * @throws ValidationException
     */
    public static function fromQuery(array $query): self
    {
        $errors = [];

        $page = self::intParam($query, 'page', 1, 1, \PHP_INT_MAX, $errors);
        $limit = self::intParam($query, 'limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT, $errors);
        $priceMin = self::priceParam($query, 'price_min', $errors);
        $priceMax = self::priceParam($query, 'price_max', $errors);

        if (null !== $priceMin && null !== $priceMax && (float) $priceMin > (float) $priceMax) {
            $errors['price_min'] = 'price_min must be less than or equal to price_max';
        }

        $name = isset($query['name']) && \is_string($query['name']) ? trim($query['name']) : null;
        if (null !== $name && mb_strlen($name) > 255) {
            $errors['name'] = 'name must be at most 255 characters';
        }

        if ([] !== $errors) {
            throw new ValidationException('Invalid query parameters', $errors);
        }

        return new self($page, $limit, '' === $name ? null : $name, $priceMin, $priceMax);
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
            $errors[$key] = \sprintf('%s must be an integer between %d and %d', $key, $min, $max);

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
            $errors[$key] = \sprintf('%s must be a non-negative number', $key);

            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }
}
