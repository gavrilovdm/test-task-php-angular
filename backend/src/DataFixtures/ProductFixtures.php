<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use App\Service\Import\ProductRowMapper;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

/** Seeds the `products` table. */
final class ProductFixtures extends AbstractFixture
{
    public const COUNT = 30;
    public const REFERENCE_PREFIX = 'product-';

    private const NAMES = ['Футболка', 'Худи', 'Леггинсы', 'Бермуды', 'Боди', 'Носки', 'Платье', 'Джоггеры'];
    private const COLORS = ['Nero', 'Bianco', 'Fumo', 'Bordo', 'Militare', 'Caramello'];
    private const SIZES = ['42 (XS)', '44 (S)', '46 (M)', '48 (L)', '50 (XL)'];

    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= self::COUNT; ++$i) {
            $price = 500 + ($i * 37) % 2000;
            $purchase = round($price * (0.5 + ($i % 4) * 0.1), 2);

            $product = (new Product(\sprintf('FIXTURE-%04d', $i)))
                ->setName(\sprintf('%s %s, %s', self::NAMES[$i % \count(self::NAMES)], self::COLORS[$i % \count(self::COLORS)], self::SIZES[$i % \count(self::SIZES)]))
                ->setDescription('Демонстрационный товар №'.$i.', созданный сидером.')
                ->setPrice(number_format($price, 2, '.', ''))
                ->setDiscount(ProductRowMapper::calculateDiscount((float) $price, $purchase));

            $manager->persist($product);
            $this->addReference(self::REFERENCE_PREFIX.$i, $product);
        }

        $manager->flush();
    }
}
