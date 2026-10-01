<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Exception\AuthenticationException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/** Stateless JWT authentication of the administrator account configured via env. */
final class AuthService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $adminEmail,
        private readonly string $adminPasswordHash,
        private readonly string $secret,
        private readonly int $ttl,
    ) {
    }

    /** @throws AuthenticationException */
    public function login(string $email, string $password): AuthToken
    {
        $validEmail = hash_equals(mb_strtolower($this->adminEmail), mb_strtolower(trim($email)));
        // password_verify runs even for a wrong email to keep response time uniform.
        $validPassword = password_verify($password, $this->adminPasswordHash);
        if (!$validEmail || !$validPassword) {
            throw new AuthenticationException('Неверный email или пароль');
        }

        $issuedAt = new \DateTimeImmutable();
        $expiresAt = $issuedAt->modify(\sprintf('+%d seconds', $this->ttl));
        $token = JWT::encode(
            ['sub' => $this->adminEmail, 'iat' => $issuedAt->getTimestamp(), 'exp' => $expiresAt->getTimestamp()],
            $this->secret,
            self::ALGORITHM,
        );

        return new AuthToken($token, $expiresAt, $this->adminEmail);
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
