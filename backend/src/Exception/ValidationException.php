<?php

declare(strict_types=1);

namespace App\Exception;

final class ValidationException extends AppException
{
    /**
     * @param array<string, string> $errors field => message
     */
    public function __construct(string $message, private readonly array $errors = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getDetails(): array
    {
        return ['errors' => $this->errors];
    }
}
