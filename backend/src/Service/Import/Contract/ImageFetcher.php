<?php

declare(strict_types=1);

namespace App\Service\Import\Contract;

use App\Service\Import\Image\FetchedImage;

/** Downloads remote resources. */
interface ImageFetcher
{
    /**
     * @param list<string> $urls
     *
     * @return array<string, FetchedImage> keyed by url
     */
    public function fetchAll(array $urls): array;
}
