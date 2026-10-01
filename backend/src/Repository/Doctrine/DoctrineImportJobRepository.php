<?php

declare(strict_types=1);

namespace App\Repository\Doctrine;

use App\Entity\ImportJob;
use App\Repository\ImportJobRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineImportJobRepository implements ImportJobRepository
{
    /** Fields written by saveProgress(); column names and types come from the ORM mapping. */
    private const PROGRESS_FIELDS = [
        'status', 'totalRows', 'processedRows', 'createdCount', 'updatedCount', 'failedCount', 'errors', 'startedAt', 'finishedAt',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(string $id): ?ImportJob
    {
        return $this->em->find(ImportJob::class, $id);
    }

    public function findLatest(int $limit): array
    {
        /** @var list<ImportJob> */
        return $this->em->createQueryBuilder()
            ->select('j')
            ->from(ImportJob::class, 'j')
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(ImportJob $job): void
    {
        $this->em->persist($job);
        $this->em->flush();
    }

    /**
     * Uses a plain DBAL UPDATE: the importer clears the persistence session between rows,
     * and a failed flush closes the EntityManager — progress must still be recorded.
     */
    public function saveProgress(ImportJob $job): void
    {
        $metadata = $this->em->getClassMetadata(ImportJob::class);
        $data = [];
        $types = [];
        foreach (self::PROGRESS_FIELDS as $field) {
            $column = $metadata->getColumnName($field);
            $data[$column] = $metadata->getFieldValue($job, $field);
            $types[$column] = $metadata->getTypeOfField($field);
        }

        $this->em->getConnection()->update(
            $metadata->getTableName(),
            $data,
            [$metadata->getSingleIdentifierColumnName() => $job->getId()],
            $types,
        );
    }
}
