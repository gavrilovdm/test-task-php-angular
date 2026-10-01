<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class AuthToken
{
    public function __construct(
        public string $token,
        public \DateTimeImmutable $expiresAt,
        public string $email,
    ) {
    }
}
