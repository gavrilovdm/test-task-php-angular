<?php

declare(strict_types=1);

namespace App\Service\Import\Image;

use App\Service\Import\Contract\ImageFetcher;
use App\Service\Import\Contract\ImageStorage;

/**
 * Makes local copies of product images: reuses already stored files, downloads the rest,
 * checks that the content really is an image and stores it.
 */
final class ImageDownloader
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly ImageFetcher $fetcher,
        private readonly ImageStorage $storage,
        private readonly int $maxBytes = 20 * 1024 * 1024,
    ) {
    }

    /**
     * @param list<string> $urls
     *
     * @return list<ImageDownloadResult> in the input order, without duplicates
     */
    public function downloadAll(array $urls): array
    {
        $urls = array_values(array_unique($urls));

        $stored = [];
        $missing = [];
        foreach ($urls as $url) {
            $path = $this->storage->find($url);
            null === $path ? $missing[] = $url : $stored[$url] = $path;
        }

        $fetched = [] === $missing ? [] : $this->fetcher->fetchAll($missing);

        return array_map(
            fn (string $url): ImageDownloadResult => isset($stored[$url])
                ? new ImageDownloadResult($url, $stored[$url])
                : $this->save($fetched[$url] ?? FetchedImage::failure($url, 'Ошибка загрузки')),
            $urls,
        );
    }

    private function save(FetchedImage $image): ImageDownloadResult
    {
        if (null === $image->content) {
            return new ImageDownloadResult($image->url, null, $image->error);
        }
        if ('' === $image->content) {
            return new ImageDownloadResult($image->url, null, 'Пустой ответ сервера');
        }
        if (\strlen($image->content) > $this->maxBytes) {
            return new ImageDownloadResult($image->url, null, 'Размер изображения превышает допустимый');
        }

        // Servers often send a generic Content-Type (e.g. binary/octet-stream), so trust the actual bytes.
        $mime = (string) (new \finfo(\FILEINFO_MIME_TYPE))->buffer($image->content);
        $extension = self::EXTENSIONS[$mime] ?? null;
        if (null === $extension) {
            return new ImageDownloadResult($image->url, null, \sprintf('Содержимое не является изображением (%s)', $mime));
        }

        try {
            return new ImageDownloadResult($image->url, $this->storage->store($image->url, $image->content, $extension));
        } catch (\RuntimeException) {
            return new ImageDownloadResult($image->url, null, 'Не удалось сохранить файл');
        }
    }
}
