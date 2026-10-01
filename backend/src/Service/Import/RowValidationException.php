<?php

declare(strict_types=1);

namespace App\Service\Import;

/** Thrown when a row cannot be imported; carries all field errors of that row. */
final class RowValidationException extends \RuntimeException
{
    /**
     * @param list<array{field: string, message: string}> $errors
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly ?string $externalCode,
        public readonly array $errors,
    ) {
        parent::__construct(implode('; ', array_map(static fn (array $e): string => $e['field'].': '.$e['message'], $errors)));
    }
}
