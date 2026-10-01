<?php

declare(strict_types=1);

namespace App\Persistence;

use Doctrine\DBAL\Connection;

final class DoctrineDatabaseHealthCheck implements DatabaseHealthCheck
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function isAvailable(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
