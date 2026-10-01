<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\AuthenticationException;
use App\Service\Auth\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Requires "Authorization: Bearer <jwt>" and exposes the user as the "user" request attribute. */
final class JwtAuthMiddleware implements MiddlewareInterface
{
    public const USER_ATTRIBUTE = 'user';

    public function __construct(private readonly AuthService $auth)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            throw new AuthenticationException('Требуется авторизация');
        }

        $user = $this->auth->verify($matches[1]);

        return $handler->handle($request->withAttribute(self::USER_ATTRIBUTE, $user));
    }
}
