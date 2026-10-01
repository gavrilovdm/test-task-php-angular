<?php

declare(strict_types=1);

namespace App\Service\Import\Contract;

use App\Service\Import\UploadedImportFile;

/** Keeps uploaded import files until the worker processes them. */
interface ImportFileStorage
{
    /** @return string location the reader can open */
    public function store(UploadedImportFile $file, string $jobId): string;
}
