<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ValidationException;
use App\Service\Import\ImportUploadValidator;
use App\Service\Import\UploadedImportFile;
use PHPUnit\Framework\TestCase;

final class ImportUploadValidatorTest extends TestCase
{
    private const SAMPLE = __DIR__.'/../fixtures/products.xlsx';
    private const MAX = 10 * 1024 * 1024;

    private static function file(string $path, string $name, int $error = \UPLOAD_ERR_OK): UploadedImportFile
    {
        return new UploadedImportFile($path, $name, (int) filesize($path), $error);
    }

    private static function rejectionReason(ImportUploadValidator $validator, ?UploadedImportFile $file): string
    {
        try {
            $validator->validate($file);
        } catch (ValidationException $e) {
            return $e->getErrors()['file'];
        }
        self::fail('ValidationException expected');
    }

    public function testAcceptsXlsx(): void
    {
        (new ImportUploadValidator(self::MAX))->validate(self::file(self::SAMPLE, 'products.xlsx'));
        $this->addToAssertionCount(1);
    }

    public function testRejectsMissingFile(): void
    {
        self::assertStringContainsString('обязательно', self::rejectionReason(new ImportUploadValidator(self::MAX), null));
    }

    public function testRejectsTooLargeFile(): void
    {
        self::assertStringContainsString('Размер файла', self::rejectionReason(new ImportUploadValidator(1024), self::file(self::SAMPLE, 'products.xlsx')));
    }

    public function testRejectsWrongExtension(): void
    {
        self::assertStringContainsString('.xlsx', self::rejectionReason(new ImportUploadValidator(self::MAX), self::file(self::SAMPLE, 'products.csv')));
    }

    public function testRejectsFakeXlsx(): void
    {
        $fake = (string) tempnam(sys_get_temp_dir(), 'fake');
        file_put_contents($fake, 'just text pretending to be a spreadsheet');

        self::assertStringContainsString('Недопустимый тип файла', self::rejectionReason(new ImportUploadValidator(self::MAX), self::file($fake, 'products.xlsx')));
    }

    public function testRejectsUploadError(): void
    {
        self::assertStringContainsString('Размер файла', self::rejectionReason(new ImportUploadValidator(self::MAX), self::file(self::SAMPLE, 'products.xlsx', \UPLOAD_ERR_INI_SIZE)));
    }
}
