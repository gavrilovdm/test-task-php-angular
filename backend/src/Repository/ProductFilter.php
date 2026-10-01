<?php

declare(strict_types=1);

namespace App\Repository;

/** Criteria of the product list: page, page size, name substring and price range. */
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
        if ($page < 1 || $limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Invalid pagination parameters');
        }
    }
}
