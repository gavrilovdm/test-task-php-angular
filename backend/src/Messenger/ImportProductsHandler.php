<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Repository\ImportJobRepository;
use App\Service\Import\ProductImporter;
use Psr\Log\LoggerInterface;

final class ImportProductsHandler
{
    public function __construct(
        private readonly ImportJobRepository $jobs,
        private readonly ProductImporter $importer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportProductsMessage $message): void
    {
        $job = $this->jobs->find($message->jobId);
        if (null === $job) {
            $this->logger->warning('Import job not found', ['job' => $message->jobId]);

            return;
        }
        if ($job->isFinished()) {
            $this->logger->info('Import job already finished, skipping redelivered message', ['job' => $job->getId()]);

            return;
        }

        $this->logger->info('Import started', ['job' => $job->getId(), 'file' => $job->getOriginalFilename()]);
        $this->importer->run($job);
        $this->logger->info('Import finished', [
            'job' => $job->getId(),
            'status' => $job->getStatus(),
            'created' => $job->getCreatedCount(),
            'updated' => $job->getUpdatedCount(),
            'failed' => $job->getFailedCount(),
        ]);
    }
}
