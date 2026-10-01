<?php

declare(strict_types=1);

namespace App\Service\Import;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Psr\Http\Message\ResponseInterface;

/**
 * Downloads product images concurrently and stores them under {storageDir}/{sha1(url)}.{ext}.
 * Files already on disk are reused, so re-imports don't download the same picture again.
 */
final class ImageDownloader
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $storageDir,
        private readonly string $publicPrefix,
        private readonly int $maxBytes = 20 * 1024 * 1024,
    ) {
    }

    /**
     * @param list<string> $urls
     *
     * @return array<string, ImageDownloadResult> keyed by url, in the input order
     */
    public function downloadAll(array $urls): array
    {
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0o775, true) && !is_dir($this->storageDir)) {
            throw new \RuntimeException(\sprintf('Cannot create image storage directory "%s"', $this->storageDir));
        }

        $results = [];
        $promises = [];
        foreach (array_unique($urls) as $url) {
            $existing = $this->findExisting($url);
            if (null !== $existing) {
                $results[$url] = new ImageDownloadResult($url, $this->publicPrefix.'/'.$existing);
                continue;
            }
            $results[$url] = null;
            $promises[$url] = $this->http->requestAsync('GET', $url)
                ->then(fn (ResponseInterface $response): ImageDownloadResult => $this->store($url, $response));
        }

        foreach (Utils::settle($promises)->wait() as $url => $outcome) {
            $results[$url] = PromiseInterface::FULFILLED === $outcome['state']
                ? $outcome['value']
                : new ImageDownloadResult((string) $url, null, $this->describeError($outcome['reason']));
        }

        /** @var array<string, ImageDownloadResult> $results */
        return $results;
    }

    private function store(string $url, ResponseInterface $response): ImageDownloadResult
    {
        $body = (string) $response->getBody();
        if ('' === $body) {
            return new ImageDownloadResult($url, null, 'Пустой ответ сервера');
        }
        if (\strlen($body) > $this->maxBytes) {
            return new ImageDownloadResult($url, null, 'Размер изображения превышает допустимый');
        }

        // Servers often send a generic Content-Type (e.g. binary/octet-stream), so trust the actual bytes.
        $contentType = (string) (new \finfo(\FILEINFO_MIME_TYPE))->buffer($body);
        $extension = self::EXTENSIONS[$contentType] ?? null;
        if (null === $extension) {
            return new ImageDownloadResult($url, null, \sprintf('Содержимое не является изображением (%s)', $contentType));
        }

        $fileName = sha1($url).'.'.$extension;
        $tmp = $this->storageDir.'/.'.$fileName.'.'.bin2hex(random_bytes(4));
        if (false === file_put_contents($tmp, $body) || !rename($tmp, $this->storageDir.'/'.$fileName)) {
            @unlink($tmp);

            return new ImageDownloadResult($url, null, 'Не удалось сохранить файл');
        }

        return new ImageDownloadResult($url, $this->publicPrefix.'/'.$fileName);
    }

    private function findExisting(string $url): ?string
    {
        $matches = glob($this->storageDir.'/'.sha1($url).'.*') ?: [];

        return [] === $matches ? null : basename($matches[0]);
    }

    private function describeError(mixed $reason): string
    {
        if ($reason instanceof RequestException && null !== $reason->getResponse()) {
            return \sprintf('Сервер вернул HTTP %d', $reason->getResponse()->getStatusCode());
        }
        if ($reason instanceof \Throwable) {
            return 'Ошибка загрузки: '.$reason->getMessage();
        }

        return 'Ошибка загрузки';
    }
}
