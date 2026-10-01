<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ValidationException;
use App\Service\Import\ImportUploadValidator;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

final class ImportUploadValidatorTest extends TestCase
{
    private const SAMPLE = __DIR__.'/../fixtures/products.xlsx';

    private function upload(string $sourcePath, string $clientName, int $error = \UPLOAD_ERR_OK, ?int $size = null): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        copy($sourcePath, (string) $tmp);

        return new UploadedFile((new StreamFactory())->createStreamFromFile((string) $tmp), $clientName, null, $size ?? filesize((string) $tmp), $error);
    }

    public function testAcceptsXlsx(): void
    {
        (new ImportUploadValidator(10 * 1024 * 1024))->validate($this->upload(self::SAMPLE, 'products.xlsx'));
        $this->addToAssertionCount(1);
    }

    public function testRejectsMissingFile(): void
    {
        $this->expectException(ValidationException::class);
        (new ImportUploadValidator(1024))->validate(null);
    }

    public function testRejectsTooLargeFile(): void
    {
        $this->expectExceptionObject(new ValidationException('Невалидный файл'));
        try {
            (new ImportUploadValidator(1024))->validate($this->upload(self::SAMPLE, 'products.xlsx'));
        } catch (ValidationException $e) {
            self::assertStringContainsString('Размер файла', $e->getErrors()['file']);
            throw $e;
        }
    }

    public function testRejectsWrongExtension(): void
    {
        try {
            (new ImportUploadValidator(10 * 1024 * 1024))->validate($this->upload(self::SAMPLE, 'products.csv'));
            self::fail('ValidationException expected');
        } catch (ValidationException $e) {
            self::assertStringContainsString('.xlsx', $e->getErrors()['file']);
        }
    }

    public function testRejectsFakeXlsx(): void
    {
        $fake = (string) tempnam(sys_get_temp_dir(), 'fake');
        file_put_contents($fake, 'just text pretending to be a spreadsheet');

        try {
            (new ImportUploadValidator(10 * 1024 * 1024))->validate($this->upload($fake, 'products.xlsx'));
            self::fail('ValidationException expected');
        } catch (ValidationException $e) {
            self::assertStringContainsString('Недопустимый тип файла', $e->getErrors()['file']);
        }
    }

    public function testRejectsUploadError(): void
    {
        $this->expectException(ValidationException::class);
        (new ImportUploadValidator(10 * 1024 * 1024))->validate($this->upload(self::SAMPLE, 'products.xlsx', \UPLOAD_ERR_INI_SIZE));
    }
}
