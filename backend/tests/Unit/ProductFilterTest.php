<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ValidationException;
use App\Repository\ProductFilter;
use PHPUnit\Framework\TestCase;

final class ProductFilterTest extends TestCase
{
    public function testDefaults(): void
    {
        $filter = ProductFilter::fromQuery([]);

        self::assertSame(1, $filter->page);
        self::assertSame(20, $filter->limit);
        self::assertNull($filter->name);
        self::assertNull($filter->priceMin);
        self::assertNull($filter->priceMax);
    }

    public function testParsesAllParameters(): void
    {
        $filter = ProductFilter::fromQuery(['page' => '3', 'limit' => '50', 'name' => '  бермуды ', 'price_min' => '100,5', 'price_max' => '2000']);

        self::assertSame(3, $filter->page);
        self::assertSame(50, $filter->limit);
        self::assertSame('бермуды', $filter->name);
        self::assertSame('100.50', $filter->priceMin);
        self::assertSame('2000.00', $filter->priceMax);
    }

    public function testRejectsInvalidValues(): void
    {
        try {
            ProductFilter::fromQuery(['page' => '0', 'limit' => '1000', 'price_min' => 'abc', 'price_max' => '-1']);
            self::fail('ValidationException expected');
        } catch (ValidationException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertEqualsCanonicalizing(['page', 'limit', 'price_min', 'price_max'], array_keys($e->getErrors()));
        }
    }

    public function testRejectsInvertedPriceRange(): void
    {
        $this->expectException(ValidationException::class);

        ProductFilter::fromQuery(['price_min' => '500', 'price_max' => '100']);
    }
}
