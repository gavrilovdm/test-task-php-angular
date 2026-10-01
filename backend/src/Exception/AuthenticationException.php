<?php

declare(strict_types=1);

namespace App\Exception;

final class AuthenticationException extends AppException
{
    public function __construct(string $message = 'Требуется авторизация')
    {
        parent::__construct($message);
    }
}
