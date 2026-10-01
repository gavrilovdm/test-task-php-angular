<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\ApiException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;

/** Renders every exception as a JSON body: {"error": "...", ...details}. */
final class JsonErrorHandler
{
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
        $status = 500;
        $payload = ['error' => 'Внутренняя ошибка сервера'];

        if ($exception instanceof ApiException) {
            $status = $exception->getStatusCode();
            $payload = ['error' => $exception->getMessage(), ...$exception->getDetails()];
        } elseif ($exception instanceof HttpException) {
            $status = $exception->getCode();
            $payload = ['error' => $exception->getMessage()];
        } else {
            $this->logger->error($exception->getMessage(), ['exception' => $exception, 'uri' => (string) $request->getUri()]);
            if ($displayErrorDetails) {
                $payload['details'] = $exception::class.': '.$exception->getMessage();
            }
        }

        return JsonResponder::json($this->responseFactory->createResponse(), $payload, $status);
    }
}
