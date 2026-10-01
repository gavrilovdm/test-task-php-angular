<?php

declare(strict_types=1);

namespace App\Repository\Doctrine;

use App\Domain\Page;
use App\Entity\Product;
use App\Repository\ProductFilter;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

final class DoctrineProductRepository implements ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findWithRelations(int $id): ?Product
    {
        /** @var Product|null */
        return $this->em->createQueryBuilder()
            ->select('p', 'a', 'i')
            ->from(Product::class, 'p')
            ->leftJoin('p.attributes', 'a')
            ->leftJoin('p.images', 'i')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByExternalCode(string $externalCode): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy(['externalCode' => $externalCode]);
    }

    public function paginate(ProductFilter $filter): Page
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p', 'i')
            ->from(Product::class, 'p')
            ->leftJoin('p.images', 'i')
            ->orderBy('p.id', 'ASC')
            ->setFirstResult(($filter->page - 1) * $filter->limit)
            ->setMaxResults($filter->limit);

        if (null !== $filter->name && '' !== $filter->name) {
            $qb->andWhere('LOWER(p.name) LIKE :name')
                ->setParameter('name', '%'.mb_strtolower(addcslashes($filter->name, '%_\\')).'%');
        }
        if (null !== $filter->priceMin) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $filter->priceMin);
        }
        if (null !== $filter->priceMax) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $filter->priceMax);
        }

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);

        /** @var list<Product> $items */
        $items = iterator_to_array($paginator->getIterator(), false);

        return new Page($items, \count($paginator), $filter->page, $filter->limit);
    }

    public function save(Product $product, bool $flush = true): void
    {
        $this->em->persist($product);
        if ($flush) {
            $this->em->flush();
        }
    }

    public function remove(Product $product, bool $flush = true): void
    {
        $this->em->remove($product);
        if ($flush) {
            $this->em->flush();
        }
    }
}
