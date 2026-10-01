<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Page;
use App\Entity\Product;

interface ProductRepository
{
    public function findWithRelations(int $id): ?Product;

    public function findByExternalCode(string $externalCode): ?Product;

    /** @return Page<Product> */
    public function paginate(ProductFilter $filter): Page;

    /** Schedules the product for saving; written on the next flush (immediately when $flush is true). */
    public function save(Product $product, bool $flush = true): void;

    public function remove(Product $product, bool $flush = true): void;
}
