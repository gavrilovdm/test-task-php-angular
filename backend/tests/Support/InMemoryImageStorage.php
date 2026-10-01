<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Import\Contract\ImageStorage;

final class InMemoryImageStorage implements ImageStorage
{
    /** @var array<string, string> key => public path */
    public array $stored = [];

    public function find(string $key): ?string
    {
        return $this->stored[$key] ?? null;
    }

    public function store(string $key, string $content, string $extension): string
    {
        return $this->stored[$key] = '/img/'.sha1($key).'.'.$extension;
    }
}
