<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\AuthenticationException;
use App\Service\Auth\AuthService;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private AuthService $auth;

    protected function setUp(): void
    {
        $hash = password_hash('secret', \PASSWORD_BCRYPT, ['cost' => 4]);
        $this->auth = new AuthService('admin@example.com', $hash, str_repeat('k', 40), 60);
    }

    public function testIssuesAndVerifiesToken(): void
    {
        $token = $this->auth->login(' Admin@Example.com ', 'secret');

        self::assertSame('admin@example.com', $token->email);
        self::assertGreaterThan(new \DateTimeImmutable(), $token->expiresAt);
        self::assertSame('admin@example.com', $this->auth->verify($token->token));
    }

    public function testRejectsWrongPassword(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->auth->login('admin@example.com', 'wrong');
    }

    public function testRejectsTamperedToken(): void
    {
        $token = $this->auth->login('admin@example.com', 'secret')->token;

        $this->expectException(AuthenticationException::class);
        $this->auth->verify($token.'x');
    }
}
