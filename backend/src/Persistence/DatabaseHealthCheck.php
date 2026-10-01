<?php

declare(strict_types=1);

namespace App\Persistence;

interface DatabaseHealthCheck
{
    public function isAvailable(): bool;
}
