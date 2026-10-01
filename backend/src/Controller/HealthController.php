<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponder;
use App\Persistence\DatabaseHealthCheck;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __construct(private readonly DatabaseHealthCheck $database)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $healthy = $this->database->isAvailable();

        return JsonResponder::json($response, [
            'status' => $healthy ? 'ok' : 'error',
            'checks' => ['database' => $healthy ? 'ok' : 'unavailable'],
            'time' => date(\DATE_ATOM),
        ], $healthy ? 200 : 503);
    }
}
