<?php

declare(strict_types=1);

namespace App\Service\Import\Source;

use App\Service\Import\Contract\ProductSourceReader;
use App\Service\Import\InvalidImportFileException;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Reads the first sheet of an .xlsx file; cells are returned as trimmed strings keyed by header. */
final class XlsxProductReader implements ProductSourceReader
{
    public function read(string $path): ProductSource
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            /** @var array<int, array<int, mixed>> $data */
            $data = $reader->load($path)->getSheet(0)->toArray(null, true, true, false);
        } catch (SpreadsheetException $e) {
            throw new InvalidImportFileException('Не удалось прочитать xlsx-файл: '.$e->getMessage(), 0, $e);
        }

        if ([] === $data) {
            throw new InvalidImportFileException('Файл пустой');
        }

        $headers = array_map(static fn (mixed $h): string => trim(self::stringify($h)), array_shift($data));

        $rows = [];
        foreach ($data as $index => $cells) {
            $row = [];
            foreach ($headers as $col => $header) {
                if ('' !== $header) {
                    $row[$header] = trim(self::stringify($cells[$col] ?? null));
                }
            }
            if ('' !== implode('', $row)) {
                $rows[$index + 2] = $row; // +1 for the header row, +1 for 1-based numbering
            }
        }

        return new ProductSource(array_values($headers), $rows);
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            null === $value => '',
            \is_bool($value) => $value ? '1' : '0',
            \is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }
}
