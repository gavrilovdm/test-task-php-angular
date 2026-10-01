<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use App\Repository\ProductFilter;
use App\Repository\ProductRepository;
use App\Tests\Support\DatabaseTestCase;

/** CRUD operations on products and their relations. */
final class ProductRepositoryTest extends DatabaseTestCase
{
    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->container->get(ProductRepository::class);
    }

    private function createProduct(string $code, string $name, string $price): Product
    {
        $product = (new Product($code))->setName($name)->setPrice($price)->setDiscount('10.00');
        $this->repository->save($product);

        return $product;
    }

    public function testCreateAndRead(): void
    {
        $product = (new Product('EXT-1'))->setName('Футболка')->setDescription('Хлопок')->setPrice('999.90')->setDiscount('25.50');
        $product->addAttribute('Цвет', 'Nero');
        $product->addImage('http://img.test/1.jpg', '/uploads/products/1.jpg');
        $this->repository->save($product);
        $this->em->clear();

        $loaded = $this->repository->findWithRelations((int) $product->getId());

        self::assertNotNull($loaded);
        self::assertSame('EXT-1', $loaded->getExternalCode());
        self::assertSame('Футболка', $loaded->getName());
        self::assertSame('999.90', $loaded->getPrice());
        self::assertSame('25.50', $loaded->getDiscount());
        self::assertSame('Nero', $loaded->getAttributes()->first() instanceof ProductAttribute ? $loaded->getAttributes()->first()->getValue() : null);
        self::assertSame('/uploads/products/1.jpg', $loaded->getImages()->first() instanceof ProductImage ? $loaded->getImages()->first()->getPath() : null);
        self::assertSame($loaded->getId(), $this->repository->findByExternalCode('EXT-1')?->getId());
    }

    public function testUpdateReplacesRelations(): void
    {
        $product = $this->createProduct('EXT-2', 'Old', '100.00');
        $product->addAttribute('Цвет', 'Bianco');
        $this->repository->save($product);

        $product->setName('New')->setPrice('150.00');
        $product->replaceAttributes(['Размер' => 'L', 'Бренд' => 'OMSA']);
        $product->replaceImages([['url' => 'http://img.test/2.jpg', 'path' => null]]);
        $this->repository->save($product);
        $this->em->clear();

        $loaded = $this->repository->findWithRelations((int) $product->getId());
        self::assertNotNull($loaded);
        self::assertSame('New', $loaded->getName());
        self::assertSame('150.00', $loaded->getPrice());
        self::assertSame(['Размер', 'Бренд'], $loaded->getAttributes()->map(static fn (ProductAttribute $a) => $a->getKey())->getValues());
        self::assertCount(1, $loaded->getImages());
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM product_attributes'), 'orphaned attributes are deleted');
    }

    public function testDeleteCascadesToRelations(): void
    {
        $product = $this->createProduct('EXT-3', 'To delete', '10.00');
        $product->addAttribute('Цвет', 'Nero');
        $product->addImage('http://img.test/3.jpg', null);
        $this->repository->save($product);

        $this->repository->remove($product);

        self::assertNull($this->repository->findByExternalCode('EXT-3'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM product_attributes'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM product_images'));
    }

    public function testExternalCodeIsUnique(): void
    {
        $this->createProduct('DUP', 'First', '1.00');

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->createProduct('DUP', 'Second', '2.00');
    }

    public function testPaginatesAndFilters(): void
    {
        foreach (range(1, 25) as $i) {
            $this->createProduct('P-'.$i, ($i % 2 ? 'Бермуды ' : 'Леггинсы ').$i, (string) ($i * 100));
        }
        $this->em->clear();

        $page = $this->repository->paginate(new ProductFilter(page: 2, limit: 10));
        self::assertSame(25, $page['total']);
        self::assertCount(10, $page['items']);
        self::assertSame('P-11', $page['items'][0]->getExternalCode());

        $byName = $this->repository->paginate(new ProductFilter(name: 'бермуды'));
        self::assertSame(13, $byName['total'], 'case-insensitive name search');

        $byPrice = $this->repository->paginate(new ProductFilter(priceMin: '500.00', priceMax: '1000.00'));
        self::assertSame(6, $byPrice['total']);

        $combined = $this->repository->paginate(new ProductFilter(name: 'Леггинсы', priceMin: '2000.00'));
        self::assertSame(['P-20', 'P-22', 'P-24'], array_map(static fn (Product $p) => $p->getExternalCode(), $combined['items']));
    }

    public function testNameSearchEscapesWildcards(): void
    {
        $this->createProduct('W-1', 'Скидка 100%', '1.00');
        $this->createProduct('W-2', 'Скидка 1000', '1.00');

        self::assertSame(1, $this->repository->paginate(new ProductFilter(name: '100%'))['total']);
    }
}
