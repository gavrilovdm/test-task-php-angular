<?php

declare(strict_types=1);

namespace App\Service\Import\Source;

/** Parsed import file: header names and data rows keyed by their row number in the file. */
final readonly class ProductSource
{
    /**
     * @param list<string>                      $headers
     * @param array<int, array<string, string>> $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
    ) {
    }

    public function rowCount(): int
    {
        return \count($this->rows);
    }
}
