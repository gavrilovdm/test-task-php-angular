<?php

declare(strict_types=1);

namespace App\Service\Import\Image;

use App\Service\Import\Contract\ImageStorage;

/** Stores images in a public directory as {sha1(key)}.{ext}. */
final class LocalImageStorage implements ImageStorage
{
    public function __construct(
        private readonly string $directory,
        private readonly string $publicPrefix,
    ) {
    }

    public function find(string $key): ?string
    {
        $matches = glob($this->directory.'/'.sha1($key).'.*') ?: [];

        return [] === $matches ? null : $this->publicPrefix.'/'.basename($matches[0]);
    }

    public function store(string $key, string $content, string $extension): string
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(\sprintf('Cannot create image directory "%s"', $this->directory));
        }

        $fileName = sha1($key).'.'.$extension;
        // Write to a temp file and rename: concurrent readers never see a partially written image.
        $tmp = $this->directory.'/.'.$fileName.'.'.bin2hex(random_bytes(4));
        if (false === file_put_contents($tmp, $content) || !rename($tmp, $this->directory.'/'.$fileName)) {
            @unlink($tmp);
            throw new \RuntimeException(\sprintf('Cannot write image "%s"', $fileName));
        }

        return $this->publicPrefix.'/'.$fileName;
    }
}
