<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Base class of expected application errors. It carries no transport details:
 * the HTTP layer (JsonErrorHandler) decides which status code each subtype maps to.
 */
abstract class AppException extends \RuntimeException
{
    /** @return array<string, mixed> extra data for the client */
    public function getDetails(): array
    {
        return [];
    }
}
