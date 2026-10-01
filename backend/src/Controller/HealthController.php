<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponder;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            $database = 'ok';
        } catch (\Throwable) {
            $database = 'unavailable';
        }

        $healthy = 'ok' === $database;

        return JsonResponder::json($response, [
            'status' => $healthy ? 'ok' : 'error',
            'checks' => ['database' => $database],
            'time' => date(\DATE_ATOM),
        ], $healthy ? 200 : 503);
    }
}
