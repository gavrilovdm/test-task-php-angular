<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Validates a raw xlsx row and maps it to ProductRowDto.
 * Column names follow the export format of the sample file.
 */
final class ProductRowMapper
{
    public const COL_EXTERNAL_CODE = 'Внешний код';
    public const COL_NAME = 'Наименование';
    public const COL_DESCRIPTION = 'Описание';
    public const COL_PRICE = 'Цена: Цена продажи';
    public const COL_PURCHASE_PRICE = 'Закупочная цена';
    public const ATTRIBUTE_PREFIX = 'Доп. поле:';
    public const COL_PHOTOS = 'Доп. поле: Ссылки на фото';
    public const COL_PACKAGE_PHOTO = 'Доп. поле: Ссылка на упаковку';

    public const REQUIRED_COLUMNS = [self::COL_EXTERNAL_CODE, self::COL_NAME, self::COL_PRICE];

    private const MAX_CODE_LENGTH = 255;
    private const MAX_NAME_LENGTH = 500;
    private const MAX_ATTRIBUTE_KEY_LENGTH = 255;
    private const MAX_PRICE = 9_999_999_999.99;

    /**
     * @param list<string> $headers
     *
     * @throws InvalidImportFileException
     */
    public function assertHeaders(array $headers): void
    {
        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $headers));
        if ([] !== $missing) {
            throw new InvalidImportFileException('Missing required columns: '.implode(', ', $missing));
        }
    }

    /**
     * @param array<string, string> $row
     *
     * @throws RowValidationException
     */
    public function map(array $row, int $rowNumber): ProductRowDto
    {
        $errors = [];
        $warnings = [];

        $externalCode = $row[self::COL_EXTERNAL_CODE] ?? '';
        if ('' === $externalCode) {
            $errors[] = ['field' => self::COL_EXTERNAL_CODE, 'message' => 'Обязательное поле не заполнено'];
        } elseif (mb_strlen($externalCode) > self::MAX_CODE_LENGTH) {
            $errors[] = ['field' => self::COL_EXTERNAL_CODE, 'message' => 'Длина превышает '.self::MAX_CODE_LENGTH.' символов'];
        }

        $name = $row[self::COL_NAME] ?? '';
        if ('' === $name) {
            $errors[] = ['field' => self::COL_NAME, 'message' => 'Обязательное поле не заполнено'];
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors[] = ['field' => self::COL_NAME, 'message' => 'Длина превышает '.self::MAX_NAME_LENGTH.' символов'];
        }

        $price = self::parseMoney($row[self::COL_PRICE] ?? '');
        if (null === $price || $price <= 0.0) {
            $errors[] = ['field' => self::COL_PRICE, 'message' => \sprintf('Некорректная цена "%s": ожидается положительное число', $row[self::COL_PRICE] ?? '')];
        }

        $purchaseRaw = $row[self::COL_PURCHASE_PRICE] ?? '';
        $purchase = self::parseMoney($purchaseRaw);
        $discount = null;
        if ('' !== $purchaseRaw && (null === $purchase || $purchase < 0.0)) {
            $errors[] = ['field' => self::COL_PURCHASE_PRICE, 'message' => \sprintf('Некорректная закупочная цена "%s"', $purchaseRaw)];
        } elseif (null !== $purchase && null !== $price && $price > 0.0) {
            if ($purchase > $price) {
                $errors[] = ['field' => self::COL_PURCHASE_PRICE, 'message' => 'Закупочная цена больше цены продажи — скидка не может быть отрицательной'];
            } else {
                $discount = self::calculateDiscount($price, $purchase);
            }
        }

        if ([] !== $errors) {
            throw new RowValidationException($rowNumber, '' === $externalCode ? null : $externalCode, $errors);
        }

        $attributes = [];
        foreach ($row as $column => $value) {
            if (!str_starts_with($column, self::ATTRIBUTE_PREFIX) || '' === $value) {
                continue;
            }
            $key = trim(mb_substr($column, mb_strlen(self::ATTRIBUTE_PREFIX)));
            if ('' === $key) {
                continue;
            }
            $attributes[mb_substr($key, 0, self::MAX_ATTRIBUTE_KEY_LENGTH)] = $value;
        }

        $imageUrls = [];
        foreach ([self::COL_PHOTOS, self::COL_PACKAGE_PHOTO] as $column) {
            foreach (preg_split('/[\s,;]+/', $row[$column] ?? '', -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $url) {
                if (self::isHttpUrl($url)) {
                    $imageUrls[] = $url;
                } else {
                    $warnings[] = ['field' => $column, 'message' => \sprintf('Некорректная ссылка на изображение "%s" пропущена', $url)];
                }
            }
        }

        $price = number_format((float) $price, 2, '.', '');

        $description = $row[self::COL_DESCRIPTION] ?? '';

        return new ProductRowDto(
            rowNumber: $rowNumber,
            externalCode: $externalCode,
            name: $name,
            description: '' === $description ? null : $description,
            price: $price,
            discount: $discount,
            attributes: $attributes,
            imageUrls: array_values(array_unique($imageUrls)),
            warnings: $warnings,
        );
    }

    /** Discount in % = (price − purchase) / price × 100, rounded to 2 decimals. */
    public static function calculateDiscount(float $price, float $purchase): string
    {
        return number_format(round(($price - $purchase) / $price * 100, 2), 2, '.', '');
    }

    /** Parses "1 320,50" / "1320.50" / "1320" into float; null when not a valid amount. */
    public static function parseMoney(string $raw): ?float
    {
        $normalized = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim($raw));
        if ('' === $normalized || !preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            return null;
        }
        $value = (float) $normalized;

        return $value > self::MAX_PRICE ? null : $value;
    }

    private static function isHttpUrl(string $url): bool
    {
        if (false === filter_var($url, \FILTER_VALIDATE_URL)) {
            return false;
        }

        return \in_array(strtolower((string) parse_url($url, \PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
