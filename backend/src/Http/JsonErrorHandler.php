<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\AppException;
use App\Exception\AuthenticationException;
use App\Exception\NotFoundException;
use App\Exception\ServiceUnavailableException;
use App\Exception\ValidationException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;

/** Maps exceptions to HTTP status codes and renders them as {"error": "...", ...details}. */
final class JsonErrorHandler
{
    /** @var array<class-string<AppException>, int> */
    private const STATUS_CODES = [
        ValidationException::class => 422,
        AuthenticationException::class => 401,
        NotFoundException::class => 404,
        ServiceUnavailableException::class => 503,
    ];

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        [$status, $payload] = match (true) {
            $exception instanceof AppException => [
                self::STATUS_CODES[$exception::class] ?? 400,
                ['error' => $exception->getMessage(), ...$exception->getDetails()],
            ],
            $exception instanceof HttpException => [$exception->getCode(), ['error' => $exception->getMessage()]],
            default => [500, $this->internalError($request, $exception, $displayErrorDetails)],
        };

        return JsonResponder::json($this->responseFactory->createResponse(), $payload, $status);
    }

    /** @return array<string, string> */
    private function internalError(ServerRequestInterface $request, \Throwable $exception, bool $displayDetails): array
    {
        $this->logger->error($exception->getMessage(), ['exception' => $exception, 'uri' => (string) $request->getUri()]);

        $payload = ['error' => 'Внутренняя ошибка сервера'];
        if ($displayDetails) {
            $payload['details'] = $exception::class.': '.$exception->getMessage();
        }

        return $payload;
    }
}
