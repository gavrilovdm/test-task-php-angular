<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ImportJob;
use App\Entity\Product;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use App\Service\Import\ProductImporter;
use App\Tests\Support\DatabaseTestCase;

final class ProductImporterTest extends DatabaseTestCase
{
    private const SAMPLE = __DIR__.'/../fixtures/products.xlsx';

    private function runImport(string $path): ImportJob
    {
        $job = new ImportJob(basename($path), $path);
        $this->container->get(ImportJobRepository::class)->save($job);
        $this->container->get(ProductImporter::class)->run($job);
        $this->em->clear();

        $reloaded = $this->container->get(ImportJobRepository::class)->find($job->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    private function rows(string $table): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table);
    }

    public function testImportsSampleFile(): void
    {
        $job = $this->runImport(self::SAMPLE);

        self::assertSame(ImportJob::STATUS_COMPLETED, $job->getStatus());
        self::assertSame(40, $job->getTotalRows());
        self::assertSame(40, $job->getProcessedRows());
        self::assertSame(40, $job->getCreatedCount());
        self::assertSame(0, $job->getFailedCount());
        self::assertSame(40, $this->rows('products'));
        self::assertGreaterThan(0, $this->rows('product_attributes'));

        $product = $this->container->get(ProductRepository::class)->findByExternalCode('3UHfAid1jaMiwgBuNvnsf3');
        self::assertInstanceOf(Product::class, $product);
        self::assertSame('1320.00', $product->getPrice());
        self::assertSame('33.33', $product->getDiscount());
        self::assertCount(4, $product->getImages(), '3 photos + package photo');
        self::assertNotNull($product->getImages()->first() ? $product->getImages()->first()->getPath() : null);

        // The broken link from row 34 is reported as a warning, the product is still imported.
        $warnings = array_filter($job->getErrors(), static fn (array $e) => 34 === $e['row']);
        self::assertNotEmpty($warnings);
        self::assertSame(ImportJob::LEVEL_WARNING, array_values($warnings)[0]['level']);
        self::assertStringContainsString('HTTP 403', array_values($warnings)[0]['message']);
    }

    public function testReimportUpsertsWithoutDuplicates(): void
    {
        $this->runImport(self::SAMPLE);
        $attributes = $this->rows('product_attributes');
        $images = $this->rows('product_images');

        $job = $this->runImport(self::SAMPLE);

        self::assertSame(0, $job->getCreatedCount());
        self::assertSame(40, $job->getUpdatedCount());
        self::assertSame(40, $this->rows('products'));
        self::assertSame($attributes, $this->rows('product_attributes'));
        self::assertSame($images, $this->rows('product_images'));
    }

    public function testInvalidRowsAreReportedAndDoNotStopImport(): void
    {
        $path = self::makeXlsx([
            ['Внешний код' => 'OK-1', 'Наименование' => 'Товар 1', 'Цена: Цена продажи' => '100,00', 'Закупочная цена' => '60,00', 'Доп. поле: Цвет' => 'Nero'],
            ['Внешний код' => '', 'Наименование' => 'Без кода', 'Цена: Цена продажи' => '100'],
            ['Внешний код' => 'BAD-PRICE', 'Наименование' => 'Плохая цена', 'Цена: Цена продажи' => 'дорого'],
            ['Внешний код' => 'OK-2', 'Наименование' => 'Товар 2', 'Цена: Цена продажи' => '200', 'Доп. поле: Ссылки на фото' => 'http://img.test/notimage.jpg'],
        ]);

        $job = $this->runImport($path);

        self::assertSame(ImportJob::STATUS_COMPLETED, $job->getStatus());
        self::assertSame(4, $job->getProcessedRows());
        self::assertSame(2, $job->getCreatedCount());
        self::assertSame(2, $job->getFailedCount());
        self::assertSame(2, $this->rows('products'));

        $errorsByRow = [];
        foreach ($job->getErrors() as $error) {
            $errorsByRow[$error['row']][] = $error;
        }
        self::assertSame('Внешний код', $errorsByRow[3][0]['field']);
        self::assertSame('BAD-PRICE', $errorsByRow[4][0]['externalCode']);
        self::assertSame(ImportJob::LEVEL_WARNING, $errorsByRow[5][0]['level'], 'non-image content is a warning only');

        $product = $this->container->get(ProductRepository::class)->findByExternalCode('OK-1');
        self::assertSame('40.00', $product?->getDiscount());
    }

    public function testFileWithoutRequiredColumnsFailsJob(): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([['Foo', 'Bar'], ['1', '2']]);
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        $job = $this->runImport($path);

        self::assertSame(ImportJob::STATUS_FAILED, $job->getStatus());
        self::assertStringContainsString('В файле нет обязательных колонок', $job->getErrors()[0]['message']);
        self::assertSame(0, $this->rows('products'));
    }
}
