<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\JsonErrorHandler;
use App\Http\JsonResponder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/** Limits requests per authenticated user (or client IP) and answers 429 when exceeded. */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterFactory $factory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly JsonErrorHandler $errorHandler,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(JwtAuthMiddleware::USER_ATTRIBUTE);
        $key = \is_string($user) ? 'user:'.$user : 'ip:'.($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');

        $limit = $this->factory->create($key)->consume();
        $headers = [
            'X-RateLimit-Limit' => (string) $limit->getLimit(),
            'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
            'X-RateLimit-Reset' => (string) $limit->getRetryAfter()->getTimestamp(),
        ];

        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            $response = JsonResponder::json($this->responseFactory->createResponse(), [
                'error' => 'Слишком много запросов на импорт. Повторите через '.$retryAfter.' с.',
                'retryAfter' => $retryAfter,
            ], 429)->withHeader('Retry-After', (string) $retryAfter);
        } else {
            try {
                $response = $handler->handle($request);
            } catch (\Throwable $e) {
                // Render errors here so that rate-limit headers are present on 4xx/5xx responses too.
                $response = ($this->errorHandler)($request, $e, false, true, false);
            }
        }

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
