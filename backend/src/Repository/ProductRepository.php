<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;

final class ProductRepository
{
    /** @var EntityRepository<Product> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->repository = $em->getRepository(Product::class);
    }

    public function find(int $id): ?Product
    {
        return $this->repository->find($id);
    }

    /** Loads a product together with its attributes and images (single query, no N+1). */
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
        return $this->repository->findOneBy(['externalCode' => $externalCode]);
    }

    /**
     * Server-side pagination with optional filtering by name and price range.
     *
     * @return array{items: list<Product>, total: int}
     */
    public function paginate(ProductFilter $filter): array
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

        return ['items' => $items, 'total' => \count($paginator)];
    }

    public function count(): int
    {
        return $this->repository->count([]);
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
