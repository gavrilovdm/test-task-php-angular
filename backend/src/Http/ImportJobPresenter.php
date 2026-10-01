<?php

declare(strict_types=1);

namespace App\Http;

use App\Entity\ImportJob;

final class ImportJobPresenter
{
    /** @return array<string, mixed> */
    public function present(ImportJob $job): array
    {
        return [
            'id' => $job->getId(),
            'status' => $job->getStatus(),
            'fileName' => $job->getOriginalFilename(),
            'progress' => $job->getProgress(),
            'totalRows' => $job->getTotalRows(),
            'processedRows' => $job->getProcessedRows(),
            'createdCount' => $job->getCreatedCount(),
            'updatedCount' => $job->getUpdatedCount(),
            'failedCount' => $job->getFailedCount(),
            'errors' => $job->getErrors(),
            'createdAt' => $job->getCreatedAt()->format(\DATE_ATOM),
            'startedAt' => $job->getStartedAt()?->format(\DATE_ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(\DATE_ATOM),
        ];
    }
}
