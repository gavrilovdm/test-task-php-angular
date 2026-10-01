<?php

declare(strict_types=1);

namespace App\Exception;

final class NotFoundException extends AppException
{
    public function __construct(string $message = 'Ресурс не найден')
    {
        parent::__construct($message);
    }
}
