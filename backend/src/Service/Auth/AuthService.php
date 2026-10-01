<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Exception\AuthenticationException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Stateless JWT authentication for the single administrator account configured via env.
 */
final class AuthService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $adminEmail,
        private readonly string $adminPassword,
        private readonly string $secret,
        private readonly int $ttl,
    ) {
    }

    /**
     * @return array{token: string, expiresAt: string, user: array{email: string}}
     *
     * @throws AuthenticationException
     */
    public function login(string $email, string $password): array
    {
        $validEmail = hash_equals(mb_strtolower($this->adminEmail), mb_strtolower(trim($email)));
        $validPassword = hash_equals($this->adminPassword, $password);
        if (!$validEmail || !$validPassword) {
            throw new AuthenticationException('Неверный email или пароль');
        }

        $now = time();
        $expiresAt = $now + $this->ttl;
        $token = JWT::encode(['sub' => $this->adminEmail, 'iat' => $now, 'exp' => $expiresAt], $this->secret, self::ALGORITHM);

        return [
            'token' => $token,
            'expiresAt' => date(\DATE_ATOM, $expiresAt),
            'user' => ['email' => $this->adminEmail],
        ];
    }

    /**
     * Returns the subject (user email) of a valid token.
     *
     * @throws AuthenticationException
     */
    public function verify(string $token): string
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (\Throwable) {
            throw new AuthenticationException('Недействительный или просроченный токен');
        }

        if (!isset($payload->sub) || !\is_string($payload->sub)) {
            throw new AuthenticationException('Недействительный токен');
        }

        return $payload->sub;
    }
}
