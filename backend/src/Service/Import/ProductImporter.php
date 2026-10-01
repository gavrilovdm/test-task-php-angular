<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ImportJob;
use App\Persistence\UnitOfWork;
use App\Repository\ImportJobRepository;
use App\Service\Import\Contract\ProductSourceReader;
use App\Service\Import\Image\ImageDownloader;
use App\Service\Import\Row\ProductRowMapper;
use App\Service\Import\Row\RowValidationException;
use Psr\Log\LoggerInterface;

/**
 * Runs an import job row by row. Invalid rows never stop the process —
 * they are collected into the job's report, and progress is saved after every row.
 */
final class ProductImporter
{
    public function __construct(
        private readonly ProductSourceReader $reader,
        private readonly ProductRowMapper $mapper,
        private readonly ImageDownloader $images,
        private readonly ProductWriter $writer,
        private readonly ImportJobRepository $jobs,
        private readonly UnitOfWork $unitOfWork,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(ImportJob $job): void
    {
        try {
            $source = $this->reader->read($job->getFilePath());
            $this->mapper->assertHeaders($source->headers);
        } catch (InvalidImportFileException $e) {
            $job->fail($e->getMessage());
            $this->jobs->saveProgress($job);

            return;
        }

        $job->start($source->rowCount());
        $this->jobs->saveProgress($job);
        // From here on the job is detached; its state is written via saveProgress().
        $this->unitOfWork->clear();

        foreach ($source->rows as $rowNumber => $row) {
            if (!$this->importRow($job, $row, $rowNumber)) {
                $job->fail('Импорт прерван из-за ошибки базы данных');
                $this->jobs->saveProgress($job);

                return;
            }
            $this->jobs->saveProgress($job);
        }

        $job->complete();
        $this->jobs->saveProgress($job);
    }

    /**
     * @param array<string, string> $row
     *
     * @return bool false when the persistence session is broken and the import cannot continue
     */
    private function importRow(ImportJob $job, array $row, int $rowNumber): bool
    {
        $externalCode = null;
        try {
            $dto = $this->mapper->map($row, $rowNumber);
            $externalCode = $dto->externalCode;
            foreach ($dto->warnings as $warning) {
                $job->addWarning($warning['message'], $rowNumber, $dto->externalCode, $warning['field']);
            }

            // Network I/O happens before the DB transaction to keep the transaction short.
            $images = [];
            foreach ($this->images->downloadAll($dto->imageUrls) as $result) {
                if (!$result->isSuccessful()) {
                    $job->addWarning(\sprintf('Изображение %s не загружено: %s', $result->url, $result->error), $rowNumber, $dto->externalCode, 'image');
                }
                $images[] = ['url' => $result->url, 'path' => $result->path];
            }

            $this->writer->upsert($dto, $images) ? $job->markRowCreated() : $job->markRowUpdated();
        } catch (RowValidationException $e) {
            foreach ($e->errors as $error) {
                $job->addError($error['message'], $rowNumber, $e->externalCode, $error['field']);
            }
            $job->markRowFailed();
        } catch (\Throwable $e) {
            // Details go to the log only; the client report gets a generic message.
            $this->logger->error('Import row failed', ['job' => $job->getId(), 'row' => $rowNumber, 'exception' => $e]);
            $job->addError('Внутренняя ошибка при сохранении товара', $rowNumber, $externalCode);
            $job->markRowFailed();
        } finally {
            $this->unitOfWork->clear();
        }

        return $this->unitOfWork->isOpen();
    }
}
