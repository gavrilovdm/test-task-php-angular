<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Seeds the `product_images` table (remote placeholder images, no local copy). */
final class ProductImageFixtures extends AbstractFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= ProductFixtures::COUNT; ++$i) {
            $product = $this->getReference(ProductFixtures::REFERENCE_PREFIX.$i, Product::class);
            for ($n = 0; $n < 2; ++$n) {
                $manager->persist(new ProductImage($product, \sprintf('https://picsum.photos/seed/product-%d-%d/600/800', $i, $n), null, $n));
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ProductFixtures::class];
    }
}
