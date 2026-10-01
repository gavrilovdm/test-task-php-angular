<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\ApiTestCase;

final class HealthAndAuthTest extends ApiTestCase
{
    public function testHealth(): void
    {
        $response = $this->request('GET', '/api/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', self::json($response)['status']);
    }

    public function testLoginReturnsToken(): void
    {
        $response = $this->request('POST', '/api/auth/login', json: ['email' => 'ADMIN@example.com', 'password' => 'admin123']);

        self::assertSame(200, $response->getStatusCode());
        $body = self::json($response);
        self::assertIsString($body['token']);
        self::assertSame(['email' => 'admin@example.com'], $body['user']);
    }

    public function testLoginRejectsWrongPassword(): void
    {
        $response = $this->request('POST', '/api/auth/login', json: ['email' => 'admin@example.com', 'password' => 'nope']);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testLoginValidatesInput(): void
    {
        $response = $this->request('POST', '/api/auth/login', json: []);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('email', self::json($response)['errors']);
    }

    public function testProtectedRoutesRequireToken(): void
    {
        self::assertSame(401, $this->request('GET', '/api/products')->getStatusCode());
        self::assertSame(401, $this->request('GET', '/api/products', headers: ['Authorization' => 'Bearer invalid'])->getStatusCode());
        self::assertSame(200, $this->authorized('GET', '/api/auth/me')->getStatusCode());
    }

    public function testCorsPreflight(): void
    {
        $response = $this->request('OPTIONS', '/api/products');

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testOpenApiSpecIsServed(): void
    {
        $response = $this->request('GET', '/api/docs/openapi.yaml');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('openapi: 3.0', (string) $response->getBody());
    }
}
