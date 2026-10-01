<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Exception\ValidationException;

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
    public function validate(?UploadedImportFile $file): void
    {
        if (null === $file) {
            $this->reject('Поле "file" обязательно', 'Файл не передан');
        }

        if (\UPLOAD_ERR_OK !== $file->uploadError) {
            $this->reject(\in_array($file->uploadError, [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                ? $this->tooLargeMessage()
                : 'Ошибка загрузки файла (код '.$file->uploadError.')');
        }
        if ($file->size <= 0) {
            $this->reject('Файл пустой');
        }
        if ($file->size > $this->maxFileSize) {
            $this->reject($this->tooLargeMessage());
        }
        if (!\in_array($file->extension(), self::ALLOWED_EXTENSIONS, true)) {
            $this->reject('Допустимое расширение: .xlsx');
        }
        if (!is_file($file->temporaryPath)) {
            $this->reject('Не удалось прочитать файл');
        }

        $mime = (string) (new \finfo(\FILEINFO_MIME_TYPE))->file($file->temporaryPath);
        if (!\in_array($mime, self::ALLOWED_MIME_TYPES, true) || !self::containsWorkbook($file->temporaryPath)) {
            $this->reject(\sprintf('Недопустимый тип файла "%s", ожидается xlsx', $mime));
        }
    }

    /** @throws ValidationException */
    private function reject(string $reason, string $message = 'Невалидный файл'): never
    {
        throw new ValidationException($message, ['file' => $reason]);
    }

    private static function containsWorkbook(string $path): bool
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            return false;
        }
        $found = false !== $zip->locateName('xl/workbook.xml');
        $zip->close();

        return $found;
    }

    private function tooLargeMessage(): string
    {
        return \sprintf('Размер файла превышает %d МБ', (int) round($this->maxFileSize / 1024 / 1024));
    }
}
