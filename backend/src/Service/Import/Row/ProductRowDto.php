<?php

declare(strict_types=1);

namespace App\Service\Import\Row;

/** A validated, normalized row of the import file. */
final readonly class ProductRowDto
{
    /**
     * @param array<string, string>                       $attributes key => value
     * @param list<string>                                $imageUrls
     * @param list<array{field: string, message: string}> $warnings   non-fatal problems of the row
     */
    public function __construct(
        public int $rowNumber,
        public string $externalCode,
        public string $name,
        public ?string $description,
        public string $price,
        public ?string $discount,
        public array $attributes,
        public array $imageUrls,
        public array $warnings = [],
    ) {
    }
}
