<?php

declare(strict_types=1);

namespace App\Service\Import\Contract;

/** Stores image files and returns the public path they are served from. */
interface ImageStorage
{
    /** Public path of an already stored image for this key, or null. */
    public function find(string $key): ?string;

    /** @return string public path */
    public function store(string $key, string $content, string $extension): string;
}
