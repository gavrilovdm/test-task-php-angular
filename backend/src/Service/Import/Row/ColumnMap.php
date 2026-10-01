<?php

declare(strict_types=1);

namespace App\Service\Import\Row;

/**
 * Describes which file columns hold which product data.
 * A different export format is supported by passing another ColumnMap, without touching the mapper.
 */
final readonly class ColumnMap
{
    /**
     * @param list<string> $imageColumns columns with image links (comma/space separated)
     */
    public function __construct(
        public string $externalCode,
        public string $name,
        public string $description,
        public string $price,
        public string $purchasePrice,
        public string $attributePrefix,
        public array $imageColumns,
    ) {
    }

    /** Format of the sample export file. */
    public static function default(): self
    {
        return new self(
            externalCode: 'Внешний код',
            name: 'Наименование',
            description: 'Описание',
            price: 'Цена: Цена продажи',
            purchasePrice: 'Закупочная цена',
            attributePrefix: 'Доп. поле:',
            imageColumns: ['Доп. поле: Ссылки на фото', 'Доп. поле: Ссылка на упаковку'],
        );
    }

    /**
     * @param list<string> $headers
     *
     * @return list<string> required columns absent from $headers
     */
    public function missingRequiredColumns(array $headers): array
    {
        return array_values(array_diff([$this->externalCode, $this->name, $this->price], $headers));
    }

    /** Attribute key for a "Доп. поле: <key>" column, null for other columns. */
    public function attributeKey(string $column): ?string
    {
        if (!str_starts_with($column, $this->attributePrefix)) {
            return null;
        }
        $key = trim(mb_substr($column, mb_strlen($this->attributePrefix)));

        return '' === $key ? null : $key;
    }
}
