<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Seeds the `product_attributes` table. */
final class ProductAttributeFixtures extends AbstractFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= ProductFixtures::COUNT; ++$i) {
            $product = $this->getReference(ProductFixtures::REFERENCE_PREFIX.$i, Product::class);
            [, $rest] = explode(' ', $product->getName(), 2);
            [$color, $size] = array_map('trim', explode(',', $rest, 2));

            foreach (['Цвет' => $color, 'Размер' => $size, 'Бренд' => 0 === $i % 2 ? 'MINIMI' : 'OMSA', 'Состав' => '95% Хлопок, 5% Эластан'] as $key => $value) {
                $manager->persist(new ProductAttribute($product, $key, $value));
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ProductFixtures::class];
    }
}
