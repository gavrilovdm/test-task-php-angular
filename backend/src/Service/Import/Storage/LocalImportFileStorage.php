<?php

declare(strict_types=1);

namespace App\Service\Import\Storage;

use App\Service\Import\Contract\ImportFileStorage;
use App\Service\Import\UploadedImportFile;

/** Moves uploaded files into a directory shared with the worker (docker volume). */
final class LocalImportFileStorage implements ImportFileStorage
{
    public function __construct(private readonly string $directory)
    {
    }

    public function store(UploadedImportFile $file, string $jobId): string
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(\sprintf('Cannot create imports directory "%s"', $this->directory));
        }

        $target = $this->directory.'/'.$jobId.'.'.$file->extension();
        $moved = is_uploaded_file($file->temporaryPath)
            ? move_uploaded_file($file->temporaryPath, $target)
            : rename($file->temporaryPath, $target);
        if (!$moved) {
            throw new \RuntimeException(\sprintf('Cannot store uploaded file "%s"', $file->originalName));
        }

        return $target;
    }
}
