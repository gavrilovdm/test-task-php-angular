<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Service\Import\UploadedImportFile;
use Psr\Http\Message\ServerRequestInterface;

/** Converts the PSR-7 upload into a transport-independent DTO for the service layer. */
final class UploadedImportFileFactory
{
    public const FIELD = 'file';

    public function fromRequest(ServerRequestInterface $request): ?UploadedImportFile
    {
        $file = $request->getUploadedFiles()[self::FIELD] ?? null;
        if (!$file instanceof \Psr\Http\Message\UploadedFileInterface) {
            return null;
        }
        $path = \UPLOAD_ERR_OK === $file->getError() ? $file->getStream()->getMetadata('uri') : null;

        return new UploadedImportFile(
            temporaryPath: \is_string($path) ? $path : '',
            originalName: (string) $file->getClientFilename(),
            size: $file->getSize() ?? 0,
            uploadError: $file->getError(),
        );
    }
}
