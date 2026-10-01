<?php

declare(strict_types=1);

namespace App\Messenger;

final readonly class ImportProductsMessage
{
    public function __construct(public string $jobId)
    {
    }
}
