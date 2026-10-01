<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * One page of a paginated result.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $limit,
    ) {
    }

    public function totalPages(): int
    {
        return $this->limit > 0 ? (int) ceil($this->total / $this->limit) : 0;
    }
}
