<?php

declare(strict_types=1);

namespace App\Persistence;

use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUnitOfWork implements UnitOfWork
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function transactional(callable $operation): mixed
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $result = $operation();
            $this->em->flush();
            $connection->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }

    public function clear(): void
    {
        $this->em->clear();
    }

    public function isOpen(): bool
    {
        return $this->em->isOpen();
    }
}
