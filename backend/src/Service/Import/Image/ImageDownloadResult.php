<?php

declare(strict_types=1);

namespace App\Service\Import\Image;

final readonly class ImageDownloadResult
{
    public function __construct(
        public string $url,
        public ?string $path,
        public ?string $error = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return null !== $this->path;
    }
}
