<?php

declare(strict_types=1);

namespace App\Service\Import\Image;

use App\Service\Import\Contract\ImageFetcher;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Psr\Http\Message\ResponseInterface;

/** Downloads URLs concurrently with Guzzle. */
final class HttpImageFetcher implements ImageFetcher
{
    public function __construct(private readonly ClientInterface $http)
    {
    }

    public function fetchAll(array $urls): array
    {
        $promises = [];
        foreach (array_unique($urls) as $url) {
            $promises[$url] = $this->http->requestAsync('GET', $url)
                ->then(static fn (ResponseInterface $response): FetchedImage => FetchedImage::success($url, (string) $response->getBody()));
        }

        $results = [];
        foreach (Utils::settle($promises)->wait() as $url => $outcome) {
            $results[(string) $url] = PromiseInterface::FULFILLED === $outcome['state']
                ? $outcome['value']
                : FetchedImage::failure((string) $url, self::describe($outcome['reason']));
        }

        /** @var array<string, FetchedImage> $results */
        return $results;
    }

    private static function describe(mixed $reason): string
    {
        if ($reason instanceof RequestException && null !== $reason->getResponse()) {
            return \sprintf('Сервер вернул HTTP %d', $reason->getResponse()->getStatusCode());
        }

        return $reason instanceof \Throwable ? 'Ошибка загрузки: '.$reason->getMessage() : 'Ошибка загрузки';
    }
}
