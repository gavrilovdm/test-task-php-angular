<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ImportJob;
use App\Exception\ServiceUnavailableException;
use App\Exception\ValidationException;
use App\Messenger\ImportProductsMessage;
use App\Repository\ImportJobRepository;
use App\Service\Import\Contract\ImportFileStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/** Use cases of the import API: accept a file and queue it, read job status. */
final class ImportService
{
    public function __construct(
        private readonly ImportUploadValidator $validator,
        private readonly ImportFileStorage $files,
        private readonly ImportJobRepository $jobs,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws ServiceUnavailableException
     */
    public function start(?UploadedImportFile $file): ImportJob
    {
        $this->validator->validate($file);
        \assert(null !== $file);

        $id = Uuid::v4()->toRfc4122();
        $job = new ImportJob($file->originalName, $this->files->store($file, $id), $id);
        $this->jobs->save($job);

        try {
            $this->bus->dispatch(new ImportProductsMessage($job->getId()));
        } catch (\Throwable $e) {
            $this->logger->error('Cannot queue import job', ['job' => $id, 'exception' => $e]);
            $job->fail('Не удалось поставить задачу в очередь');
            $this->jobs->save($job);
            throw new ServiceUnavailableException('Очередь импорта недоступна, попробуйте позже', 0, $e);
        }

        return $job;
    }

    public function find(string $id): ?ImportJob
    {
        return Uuid::isValid($id) ? $this->jobs->find($id) : null;
    }

    /** @return list<ImportJob> */
    public function latest(int $limit): array
    {
        return $this->jobs->findLatest($limit);
    }
}
