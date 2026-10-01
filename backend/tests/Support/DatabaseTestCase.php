<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/** Base class for tests hitting the real PostgreSQL test database; tables are emptied before each test. */
abstract class DatabaseTestCase extends TestCase
{
    protected ContainerInterface $container;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->container = TestContainer::create();
        $this->em = $this->container->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement(
            'TRUNCATE products, product_attributes, product_images, import_jobs RESTART IDENTITY CASCADE',
        );
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    /**
     * Writes an xlsx file with the sample headers and the given rows.
     *
     * @param list<array<string, string>> $rows
     */
    protected static function makeXlsx(array $rows): string
    {
        $headers = ['Внешний код', 'Наименование', 'Описание', 'Цена: Цена продажи', 'Закупочная цена', 'Доп. поле: Цвет', 'Доп. поле: Ссылки на фото'];
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        foreach ($rows as $i => $row) {
            $sheet->fromArray(array_map(static fn (string $h): string => $row[$h] ?? '', $headers), null, 'A'.($i + 2));
        }
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
