<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Exception\ValidationException;
use Psr\Http\Message\UploadedFileInterface;

/** Validates the uploaded import file: upload status, size, extension, MIME type and xlsx structure. */
final class ImportUploadValidator
{
    public const ALLOWED_EXTENSIONS = ['xlsx'];
    public const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', // older libmagic versions report xlsx as a plain zip
    ];

    public function __construct(private readonly int $maxFileSize)
    {
    }

    /** @throws ValidationException */
    public function validate(?UploadedFileInterface $file): void
    {
        if (null === $file) {
            throw new ValidationException('Файл не передан', ['file' => 'Поле "file" обязательно']);
        }

        if (\UPLOAD_ERR_OK !== $file->getError()) {
            $message = \in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                ? $this->tooLargeMessage()
                : 'Ошибка загрузки файла (код '.$file->getError().')';
            throw new ValidationException('Невалидный файл', ['file' => $message]);
        }

        $size = $file->getSize() ?? 0;
        if ($size <= 0) {
            throw new ValidationException('Невалидный файл', ['file' => 'Файл пустой']);
        }
        if ($size > $this->maxFileSize) {
            throw new ValidationException('Невалидный файл', ['file' => $this->tooLargeMessage()]);
        }

        $extension = strtolower(pathinfo((string) $file->getClientFilename(), \PATHINFO_EXTENSION));
        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new ValidationException('Невалидный файл', ['file' => 'Допустимое расширение: .xlsx']);
        }

        $path = $file->getStream()->getMetadata('uri');
        if (!\is_string($path) || !is_file($path)) {
            throw new ValidationException('Невалидный файл', ['file' => 'Не удалось прочитать файл']);
        }

        $mime = (new \finfo(\FILEINFO_MIME_TYPE))->file($path);
        if (!\in_array($mime, self::ALLOWED_MIME_TYPES, true) || !$this->looksLikeXlsx($path)) {
            throw new ValidationException('Невалидный файл', ['file' => \sprintf('Недопустимый тип файла "%s", ожидается xlsx', $mime)]);
        }
    }

    private function looksLikeXlsx(string $path): bool
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            return false;
        }
        $ok = false !== $zip->locateName('xl/workbook.xml');
        $zip->close();

        return $ok;
    }

    private function tooLargeMessage(): string
    {
        return \sprintf('Размер файла превышает %d МБ', (int) round($this->maxFileSize / 1024 / 1024));
    }
}
