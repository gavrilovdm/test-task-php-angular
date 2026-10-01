<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Import\Image\ImageDownloader;
use App\Tests\Support\FakeImageFetcher;
use App\Tests\Support\InMemoryImageStorage;
use PHPUnit\Framework\TestCase;

final class ImageDownloaderTest extends TestCase
{
    private FakeImageFetcher $fetcher;
    private InMemoryImageStorage $storage;

    protected function setUp(): void
    {
        $this->fetcher = new FakeImageFetcher();
        $this->storage = new InMemoryImageStorage();
    }

    public function testDownloadsDetectsTypeByContentAndStores(): void
    {
        $results = (new ImageDownloader($this->fetcher, $this->storage))->downloadAll(['http://img.test/a.jpg', 'http://img.test/a.jpg']);

        self::assertCount(1, $results, 'duplicates are removed');
        self::assertTrue($results[0]->isSuccessful());
        self::assertStringEndsWith('.png', (string) $results[0]->path, 'extension comes from the bytes, not the URL');
    }

    public function testReusesStoredImagesWithoutDownloading(): void
    {
        $this->storage->stored['http://img.test/cached.jpg'] = '/img/cached.jpg';

        $results = (new ImageDownloader($this->fetcher, $this->storage))->downloadAll(['http://img.test/cached.jpg']);

        self::assertSame('/img/cached.jpg', $results[0]->path);
        self::assertSame([], $this->fetcher->requested);
    }

    public function testReportsFailuresAndNonImages(): void
    {
        $results = (new ImageDownloader($this->fetcher, $this->storage))->downloadAll(['http://img.test/forbidden.jpg', 'http://img.test/notimage.jpg']);

        self::assertSame('Сервер вернул HTTP 403', $results[0]->error);
        self::assertStringContainsString('не является изображением', (string) $results[1]->error);
        self::assertSame([], $this->storage->stored);
    }

    public function testRejectsTooLargeImages(): void
    {
        $results = (new ImageDownloader($this->fetcher, $this->storage, maxBytes: 10))->downloadAll(['http://img.test/big.jpg']);

        self::assertFalse($results[0]->isSuccessful());
    }
}
