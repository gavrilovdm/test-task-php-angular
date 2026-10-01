<?php

declare(strict_types=1);

namespace App\Http;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;

/** Converts Product entities to API arrays. */
final class ProductPresenter
{
    /** @return array<string, mixed> */
    public function summary(Product $product): array
    {
        $first = $product->getImages()->first();

        return [
            'id' => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name' => $product->getName(),
            'price' => (float) $product->getPrice(),
            'discount' => null === $product->getDiscount() ? null : (float) $product->getDiscount(),
            'image' => $first instanceof ProductImage ? ($first->getPath() ?? $first->getUrl()) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Product $product): array
    {
        return [
            ...$this->summary($product),
            'description' => $product->getDescription(),
            'createdAt' => $product->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()->format(\DATE_ATOM),
            'attributes' => array_values(array_map(
                static fn (ProductAttribute $a): array => ['id' => $a->getId(), 'key' => $a->getKey(), 'value' => $a->getValue()],
                $product->getAttributes()->toArray(),
            )),
            'images' => array_values(array_map(
                static fn (ProductImage $i): array => ['id' => $i->getId(), 'url' => $i->getUrl(), 'path' => $i->getPath(), 'position' => $i->getPosition()],
                $product->getImages()->toArray(),
            )),
        ];
    }
}
