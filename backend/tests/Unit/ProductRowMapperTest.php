<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\DiscountCalculator;
use App\Service\Import\InvalidImportFileException;
use App\Service\Import\Row\ColumnMap;
use App\Service\Import\Row\MoneyParser;
use App\Service\Import\Row\ProductRowMapper;
use App\Service\Import\Row\RowValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductRowMapperTest extends TestCase
{
    private ProductRowMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ProductRowMapper(ColumnMap::default());
    }

    /**
     * @param array<string, string> $override
     *
     * @return array<string, string>
     */
    private static function validRow(array $override = []): array
    {
        return [
            'Внешний код' => 'ABC-1',
            'Наименование' => 'Бермуды мужские',
            'Описание' => 'Лёгкие и удобные',
            'Цена: Цена продажи' => '1320,00',
            'Закупочная цена' => '880,00',
            'Доп. поле: Цвет' => 'Grigio',
            'Доп. поле: Размер' => '46(M)',
            'Доп. поле: Пусто' => '',
            'Доп. поле: Ссылки на фото' => 'http://img.test/a.jpg, http://img.test/b.jpg',
            'Доп. поле: Ссылка на упаковку' => 'http://img.test/p.jpg',
            'Штрихкод EAN13' => '8000000784582',
            ...$override,
        ];
    }

    public function testMapsValidRow(): void
    {
        $dto = $this->mapper->map(self::validRow(), 2);

        self::assertSame(2, $dto->rowNumber);
        self::assertSame('ABC-1', $dto->externalCode);
        self::assertSame('Бермуды мужские', $dto->name);
        self::assertSame('Лёгкие и удобные', $dto->description);
        self::assertSame('1320.00', $dto->price);
        self::assertSame('33.33', $dto->discount);
        self::assertSame('Grigio', $dto->attributes['Цвет']);
        self::assertSame('46(M)', $dto->attributes['Размер']);
        self::assertArrayNotHasKey('Пусто', $dto->attributes, 'empty attribute values are skipped');
        self::assertArrayNotHasKey('Штрихкод EAN13', $dto->attributes, 'only "Доп. поле" columns become attributes');
        self::assertSame(['http://img.test/a.jpg', 'http://img.test/b.jpg', 'http://img.test/p.jpg'], $dto->imageUrls);
        self::assertSame([], $dto->warnings);
    }

    /** @return iterable<string, array{float, float, string}> */
    public static function discountProvider(): iterable
    {
        yield 'sample file' => [1320.0, 880.0, '33.33'];
        yield 'no margin' => [100.0, 100.0, '0.00'];
        yield 'half' => [200.0, 100.0, '50.00'];
        yield 'rounding' => [3.0, 1.0, '66.67'];
    }

    #[DataProvider('discountProvider')]
    public function testCalculatesDiscount(float $price, float $purchase, string $expected): void
    {
        self::assertSame($expected, DiscountCalculator::percent($price, $purchase));
    }

    public function testDiscountIsNullWithoutPurchasePrice(): void
    {
        self::assertNull($this->mapper->map(self::validRow(['Закупочная цена' => '']), 2)->discount);
    }

    /** @return iterable<string, array{string, ?float}> */
    public static function moneyProvider(): iterable
    {
        yield 'comma decimal' => ['1320,50', 1320.5];
        yield 'dot decimal' => ['1320.50', 1320.5];
        yield 'integer' => ['99', 99.0];
        yield 'thousands with nbsp' => ["1\u{00A0}320,00", 1320.0];
        yield 'text' => ['abc', null];
        yield 'empty' => ['', null];
        yield 'two separators' => ['1,2,3', null];
    }

    #[DataProvider('moneyProvider')]
    public function testParsesMoney(string $raw, ?float $expected): void
    {
        self::assertSame($expected, MoneyParser::parse($raw));
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalidRowProvider(): iterable
    {
        yield 'missing external code' => [['Внешний код' => ''], 'Внешний код'];
        yield 'missing name' => [['Наименование' => ''], 'Наименование'];
        yield 'invalid price' => [['Цена: Цена продажи' => 'бесплатно'], 'Цена: Цена продажи'];
        yield 'zero price' => [['Цена: Цена продажи' => '0'], 'Цена: Цена продажи'];
        yield 'invalid purchase price' => [['Закупочная цена' => 'n/a'], 'Закупочная цена'];
        yield 'purchase above price' => [['Закупочная цена' => '2000'], 'Закупочная цена'];
        yield 'too long name' => [['Наименование' => str_repeat('x', 501)], 'Наименование'];
    }

    /** @param array<string, string> $override */
    #[DataProvider('invalidRowProvider')]
    public function testRejectsInvalidRow(array $override, string $field): void
    {
        try {
            $this->mapper->map(self::validRow($override), 7);
            self::fail('RowValidationException expected');
        } catch (RowValidationException $e) {
            self::assertSame(7, $e->rowNumber);
            self::assertContains($field, array_column($e->errors, 'field'));
        }
    }

    public function testCollectsAllErrorsOfRow(): void
    {
        try {
            $this->mapper->map(self::validRow(['Наименование' => '', 'Цена: Цена продажи' => 'x']), 3);
            self::fail('RowValidationException expected');
        } catch (RowValidationException $e) {
            self::assertCount(2, $e->errors);
        }
    }

    public function testInvalidImageUrlBecomesWarning(): void
    {
        $dto = $this->mapper->map(self::validRow(['Доп. поле: Ссылки на фото' => 'not-a-url, ftp://x/y.jpg, https://ok.test/1.jpg']), 2);

        self::assertSame(['https://ok.test/1.jpg', 'http://img.test/p.jpg'], $dto->imageUrls);
        self::assertCount(2, $dto->warnings);
    }

    public function testAssertHeadersRequiresKeyColumns(): void
    {
        $this->expectException(InvalidImportFileException::class);
        $this->expectExceptionMessage('Внешний код');

        $this->mapper->assertHeaders(['Наименование', 'Цена: Цена продажи']);
    }

    public function testCustomColumnMapSupportsAnotherFormat(): void
    {
        $mapper = new ProductRowMapper(new ColumnMap('SKU', 'Title', 'Text', 'Price', 'Cost', 'Attr:', ['Photos']));

        $dto = $mapper->map(['SKU' => 'S-1', 'Title' => 'Shirt', 'Price' => '200', 'Cost' => '150', 'Attr: Color' => 'Red', 'Photos' => 'https://img.test/1.jpg'], 2);

        self::assertSame('S-1', $dto->externalCode);
        self::assertSame('25.00', $dto->discount);
        self::assertSame(['Color' => 'Red'], $dto->attributes);
        self::assertSame(['https://img.test/1.jpg'], $dto->imageUrls);
    }
}
