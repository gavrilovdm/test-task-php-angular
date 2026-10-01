<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ImportJob;
use App\Exception\ApiException;
use App\Messenger\ImportProductsMessage;
use App\Repository\ImportJobRepository;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/** Accepts an uploaded file, registers an import job and queues it for the worker. */
final class ImportService
{
    public function __construct(
        private readonly ImportUploadValidator $validator,
        private readonly ImportJobRepository $jobs,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly string $importsDir,
    ) {
    }

    public function start(?UploadedFileInterface $file): ImportJob
    {
        $this->validator->validate($file);
        \assert(null !== $file);

        if (!is_dir($this->importsDir) && !@mkdir($this->importsDir, 0o775, true) && !is_dir($this->importsDir)) {
            throw new \RuntimeException(\sprintf('Cannot create imports directory "%s"', $this->importsDir));
        }

        $id = Uuid::v4()->toRfc4122();
        $path = $this->importsDir.'/'.$id.'.xlsx';
        $file->moveTo($path);

        $job = new ImportJob((string) $file->getClientFilename(), $path, $id);
        $this->jobs->save($job);

        try {
            $this->bus->dispatch(new ImportProductsMessage($job->getId()));
        } catch (\Throwable $e) {
            $this->logger->error('Cannot queue import job', ['job' => $id, 'exception' => $e]);
            $job->fail('Не удалось поставить задачу в очередь');
            $this->jobs->save($job);
            throw new ApiException('Очередь импорта недоступна, попробуйте позже', 503, [], $e);
        }

        return $job;
    }

    public function get(string $id): ?ImportJob
    {
        return Uuid::isValid($id) ? $this->jobs->find($id) : null;
    }

    /** @return list<ImportJob> */
    public function latest(int $limit): array
    {
        return $this->jobs->findLatest($limit);
    }
}
