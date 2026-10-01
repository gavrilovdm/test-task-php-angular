<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportJob;

interface ImportJobRepository
{
    public function find(string $id): ?ImportJob;

    /** @return list<ImportJob> newest first */
    public function findLatest(int $limit): array;

    public function save(ImportJob $job): void;

    /**
     * Persists counters, status and the error report of a running job.
     * Must work even when the job entity is detached from the persistence session.
     */
    public function saveProgress(ImportJob $job): void;
}
