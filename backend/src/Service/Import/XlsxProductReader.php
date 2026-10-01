<?php

declare(strict_types=1);

namespace App\Service\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

/** Reads the first sheet of an .xlsx file as rows keyed by header names. */
final class XlsxProductReader
{
    /**
     * @return array{headers: list<string>, rows: array<int, array<string, string>>} rows keyed by spreadsheet row number
     *
     * @throws InvalidImportFileException
     */
    public function read(string $path): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (ReaderException|\PhpOffice\PhpSpreadsheet\Exception $e) {
            throw new InvalidImportFileException('Cannot read xlsx file: '.$e->getMessage(), 0, $e);
        }

        /** @var array<int, array<int, mixed>> $data */
        $data = $sheet->toArray(null, true, true, false);
        if ([] === $data) {
            throw new InvalidImportFileException('The file is empty');
        }

        $headers = array_map(static fn (mixed $h): string => trim(self::stringify($h)), array_shift($data));

        $rows = [];
        foreach ($data as $index => $cells) {
            $row = [];
            foreach ($headers as $col => $header) {
                if ('' === $header) {
                    continue;
                }
                $row[$header] = trim(self::stringify($cells[$col] ?? null));
            }
            if ('' === implode('', $row)) {
                continue; // skip fully empty lines
            }
            $rows[$index + 2] = $row; // +1 for header, +1 for 1-based numbering
        }

        return ['headers' => array_values($headers), 'rows' => $rows];
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            null === $value => '',
            \is_bool($value) => $value ? '1' : '0',
            \is_scalar($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }
}
