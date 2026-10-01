<?php

declare(strict_types=1);

namespace App\Service\Import\Image;

/** Raw result of a download: either content or an error description. */
final readonly class FetchedImage
{
    private function __construct(
        public string $url,
        public ?string $content,
        public ?string $error,
    ) {
    }

    public static function success(string $url, string $content): self
    {
        return new self($url, $content, null);
    }

    public static function failure(string $url, string $error): self
    {
        return new self($url, null, $error);
    }
}
