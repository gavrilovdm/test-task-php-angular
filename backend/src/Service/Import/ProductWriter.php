<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Product;
use App\Persistence\UnitOfWork;
use App\Repository\ProductRepository;
use App\Service\Import\Row\ProductRowDto;

/** Upserts a product by external_code together with its attributes and images in one transaction. */
final class ProductWriter
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /**
     * @param list<array{url: string, path: ?string}> $images
     *
     * @return bool true when a new product was created, false when an existing one was updated
     */
    public function upsert(ProductRowDto $row, array $images): bool
    {
        return $this->unitOfWork->transactional(function () use ($row, $images): bool {
            $product = $this->products->findByExternalCode($row->externalCode);
            $isNew = null === $product;
            $product ??= new Product($row->externalCode);

            $product->setName($row->name)
                ->setDescription($row->description)
                ->setPrice($row->price)
                ->setDiscount($row->discount);
            $product->replaceAttributes($row->attributes);
            $product->replaceImages($images);

            $this->products->save($product, flush: false);

            return $isNew;
        });
    }
}
