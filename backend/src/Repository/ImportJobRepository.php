<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportJob;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final class ImportJobRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(string $id): ?ImportJob
    {
        return $this->em->find(ImportJob::class, $id);
    }

    /** @return list<ImportJob> */
    public function findLatest(int $limit = 10): array
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
     * Persists job state through plain DBAL. The importer clears the EntityManager between rows
     * (and the EM may be closed after a failed flush), so progress must not depend on the UnitOfWork.
     */
    public function saveProgress(ImportJob $job): void
    {
        $this->em->getConnection()->update('import_jobs', [
            'status' => $job->getStatus(),
            'total_rows' => $job->getTotalRows(),
            'processed_rows' => $job->getProcessedRows(),
            'created_count' => $job->getCreatedCount(),
            'updated_count' => $job->getUpdatedCount(),
            'failed_count' => $job->getFailedCount(),
            'errors' => $job->getErrors(),
            'started_at' => $job->getStartedAt(),
            'finished_at' => $job->getFinishedAt(),
        ], ['id' => $job->getId()], [
            'errors' => Types::JSON,
            'started_at' => Types::DATETIME_IMMUTABLE,
            'finished_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }
}
