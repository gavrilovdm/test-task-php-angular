<?php

declare(strict_types=1);

namespace App\Service\Import;

/** Transport-independent description of an uploaded import file. */
final readonly class UploadedImportFile
{
    public function __construct(
        public string $temporaryPath,
        public string $originalName,
        public int $size,
        public int $uploadError = \UPLOAD_ERR_OK,
    ) {
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName, \PATHINFO_EXTENSION));
    }
}
