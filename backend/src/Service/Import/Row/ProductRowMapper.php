<?php

declare(strict_types=1);

namespace App\Service\Import\Row;

use App\Domain\DiscountCalculator;
use App\Service\Import\InvalidImportFileException;

/** Validates a raw file row and converts it into ProductRowDto according to the ColumnMap. */
final class ProductRowMapper
{
    private const MAX_CODE_LENGTH = 255;
    private const MAX_NAME_LENGTH = 500;
    private const MAX_ATTRIBUTE_KEY_LENGTH = 255;

    public function __construct(private readonly ColumnMap $columns)
    {
    }

    /**
     * @param list<string> $headers
     *
     * @throws InvalidImportFileException
     */
    public function assertHeaders(array $headers): void
    {
        $missing = $this->columns->missingRequiredColumns($headers);
        if ([] !== $missing) {
            throw new InvalidImportFileException('В файле нет обязательных колонок: '.implode(', ', $missing));
        }
    }

    /**
     * @param array<string, string> $row
     *
     * @throws RowValidationException
     */
    public function map(array $row, int $rowNumber): ProductRowDto
    {
        $c = $this->columns;
        $externalCode = $row[$c->externalCode] ?? '';
        $name = $row[$c->name] ?? '';
        $description = $row[$c->description] ?? '';

        $errors = [
            ...$this->validateText($c->externalCode, $externalCode, self::MAX_CODE_LENGTH),
            ...$this->validateText($c->name, $name, self::MAX_NAME_LENGTH),
        ];

        $price = MoneyParser::parse($row[$c->price] ?? '');
        if (null === $price || $price <= 0.0) {
            $errors[] = ['field' => $c->price, 'message' => \sprintf('Некорректная цена "%s": ожидается положительное число', $row[$c->price] ?? '')];
        }

        [$discount, $discountErrors] = $this->discount($price, $row[$c->purchasePrice] ?? '');
        $errors = [...$errors, ...$discountErrors];

        if ([] !== $errors || null === $price) {
            throw new RowValidationException($rowNumber, '' === $externalCode ? null : $externalCode, $errors);
        }

        [$imageUrls, $warnings] = $this->imageUrls($row);

        return new ProductRowDto(
            rowNumber: $rowNumber,
            externalCode: $externalCode,
            name: $name,
            description: '' === $description ? null : $description,
            price: MoneyParser::format($price),
            discount: $discount,
            attributes: $this->attributes($row),
            imageUrls: $imageUrls,
            warnings: $warnings,
        );
    }

    /** @return list<array{field: string, message: string}> */
    private function validateText(string $field, string $value, int $maxLength): array
    {
        return match (true) {
            '' === $value => [['field' => $field, 'message' => 'Обязательное поле не заполнено']],
            mb_strlen($value) > $maxLength => [['field' => $field, 'message' => \sprintf('Длина превышает %d символов', $maxLength)]],
            default => [],
        };
    }

    /**
     * Discount is optional: an empty purchase price gives null.
     *
     * @return array{0: ?string, 1: list<array{field: string, message: string}>}
     */
    private function discount(?float $price, string $purchaseRaw): array
    {
        if ('' === $purchaseRaw) {
            return [null, []];
        }
        $field = $this->columns->purchasePrice;
        $purchase = MoneyParser::parse($purchaseRaw);
        if (null === $purchase || $purchase < 0.0) {
            return [null, [['field' => $field, 'message' => \sprintf('Некорректная закупочная цена "%s"', $purchaseRaw)]]];
        }
        if (null === $price || $price <= 0.0) {
            return [null, []]; // the price error is already reported
        }
        if ($purchase > $price) {
            return [null, [['field' => $field, 'message' => 'Закупочная цена больше цены продажи — скидка не может быть отрицательной']]];
        }

        return [DiscountCalculator::percent($price, $purchase), []];
    }

    /**
     * @param array<string, string> $row
     *
     * @return array<string, string>
     */
    private function attributes(array $row): array
    {
        $attributes = [];
        foreach ($row as $column => $value) {
            $key = $this->columns->attributeKey($column);
            if (null !== $key && '' !== $value) {
                $attributes[mb_substr($key, 0, self::MAX_ATTRIBUTE_KEY_LENGTH)] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @param array<string, string> $row
     *
     * @return array{0: list<string>, 1: list<array{field: string, message: string}>} urls and warnings
     */
    private function imageUrls(array $row): array
    {
        $urls = [];
        $warnings = [];
        foreach ($this->columns->imageColumns as $column) {
            foreach (preg_split('/[\s,;]+/', $row[$column] ?? '', -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $url) {
                if (self::isHttpUrl($url)) {
                    $urls[] = $url;
                } else {
                    $warnings[] = ['field' => $column, 'message' => \sprintf('Некорректная ссылка на изображение "%s" пропущена', $url)];
                }
            }
        }

        return [array_values(array_unique($urls)), $warnings];
    }

    private static function isHttpUrl(string $url): bool
    {
        return false !== filter_var($url, \FILTER_VALIDATE_URL)
            && \in_array(strtolower((string) parse_url($url, \PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
