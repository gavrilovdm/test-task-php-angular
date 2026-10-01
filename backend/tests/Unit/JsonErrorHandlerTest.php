<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\AuthenticationException;
use App\Exception\NotFoundException;
use App\Exception\ServiceUnavailableException;
use App\Exception\ValidationException;
use App\Http\JsonErrorHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class JsonErrorHandlerTest extends TestCase
{
    /** @return iterable<string, array{\Throwable, int}> */
    public static function exceptionProvider(): iterable
    {
        yield 'validation' => [new ValidationException('Invalid', ['f' => 'bad']), 422];
        yield 'auth' => [new AuthenticationException(), 401];
        yield 'not found' => [new NotFoundException(), 404];
        yield 'unavailable' => [new ServiceUnavailableException('Queue down'), 503];
        yield 'unexpected' => [new \LogicException('secret internals'), 500];
    }

    #[DataProvider('exceptionProvider')]
    public function testMapsExceptionsToStatusCodes(\Throwable $exception, int $status): void
    {
        $handler = new JsonErrorHandler(new ResponseFactory(), new NullLogger());

        $response = $handler((new ServerRequestFactory())->createServerRequest('GET', '/'), $exception, false, false, false);

        self::assertSame($status, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringNotContainsString('secret internals', $body, 'internal details are hidden');
    }

    public function testValidationDetailsAreReturned(): void
    {
        $handler = new JsonErrorHandler(new ResponseFactory(), new NullLogger());

        $response = $handler((new ServerRequestFactory())->createServerRequest('GET', '/'), new ValidationException('Invalid', ['file' => 'too big']), false, false, false);

        self::assertSame(['error' => 'Invalid', 'errors' => ['file' => 'too big']], json_decode((string) $response->getBody(), true));
    }
}
