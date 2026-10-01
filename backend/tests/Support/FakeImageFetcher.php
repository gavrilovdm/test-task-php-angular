<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Import\Contract\ImageFetcher;
use App\Service\Import\Image\FetchedImage;

/**
 * Test double of the remote image server: every URL returns a 1×1 PNG, except
 * the broken link of the sample file (5581481) / "forbidden" URLs (HTTP 403) and "notimage" URLs (HTML).
 */
final class FakeImageFetcher implements ImageFetcher
{
    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** @var list<string> */
    public array $requested = [];

    public function fetchAll(array $urls): array
    {
        $results = [];
        foreach ($urls as $url) {
            $this->requested[] = $url;
            $results[$url] = match (true) {
                str_contains($url, '5581481'), str_contains($url, 'forbidden') => FetchedImage::failure($url, 'Сервер вернул HTTP 403'),
                str_contains($url, 'notimage') => FetchedImage::success($url, '<html></html>'),
                default => FetchedImage::success($url, (string) base64_decode(self::PNG, true)),
            };
        }

        return $results;
    }
}
