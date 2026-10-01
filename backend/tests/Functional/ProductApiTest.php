<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Tests\Support\ApiTestCase;

final class ProductApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $repository = $this->container->get(ProductRepository::class);
        foreach (range(1, 45) as $i) {
            $product = (new Product('API-'.$i))->setName(($i <= 5 ? 'Боди ' : 'Носки ').$i)->setPrice((string) ($i * 10))->setDiscount('33.33');
            $product->addAttribute('Цвет', 'Nero');
            $product->addImage('http://img.test/'.$i.'.jpg', '/uploads/products/'.$i.'.jpg');
            $repository->save($product, false);
        }
        $this->em->flush();
        $this->em->clear();
    }

    public function testListIsPaginatedOnServer(): void
    {
        $body = self::json($this->authorized('GET', '/api/products', ['page' => '3', 'limit' => '20']));

        self::assertSame(['page' => 3, 'limit' => 20, 'total' => 45, 'totalPages' => 3], $body['meta']);
        self::assertCount(5, $body['data']);
        self::assertSame('API-41', $body['data'][0]['externalCode']);
        self::assertSame('/uploads/products/41.jpg', $body['data'][0]['image']);
        self::assertSame(410.0, $body['data'][0]['price']);
    }

    public function testFiltersByNameAndPriceRange(): void
    {
        $body = self::json($this->authorized('GET', '/api/products', ['name' => 'боди', 'price_min' => '20', 'price_max' => '40']));

        self::assertSame(3, $body['meta']['total']);
        self::assertSame(['Боди 2', 'Боди 3', 'Боди 4'], array_column($body['data'], 'name'));
    }

    public function testInvalidQueryReturns422(): void
    {
        $response = $this->authorized('GET', '/api/products', ['limit' => '500']);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('limit', self::json($response)['errors']);
    }

    public function testShowReturnsFullCard(): void
    {
        $id = $this->container->get(ProductRepository::class)->findByExternalCode('API-1')?->getId();

        $body = self::json($this->authorized('GET', '/api/products/'.$id));

        self::assertSame('Боди 1', $body['name']);
        self::assertSame(33.33, $body['discount']);
        self::assertSame([['id' => $body['attributes'][0]['id'], 'key' => 'Цвет', 'value' => 'Nero']], $body['attributes']);
        self::assertSame('http://img.test/1.jpg', $body['images'][0]['url']);
        self::assertSame('/uploads/products/1.jpg', $body['images'][0]['path']);
    }

    public function testShowUnknownReturns404(): void
    {
        self::assertSame(404, $this->authorized('GET', '/api/products/999999')->getStatusCode());
    }
}
