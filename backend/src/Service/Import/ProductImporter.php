<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ImportJob;
use App\Entity\Product;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs an import job: parses the file, downloads images and upserts products by external_code.
 * Invalid rows never stop the process — they are collected into the job's error report.
 */
final class ProductImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly ImportJobRepository $jobs,
        private readonly XlsxProductReader $reader,
        private readonly ProductRowMapper $mapper,
        private readonly ImageDownloader $images,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(ImportJob $job): void
    {
        try {
            $data = $this->reader->read($job->getFilePath());
            $this->mapper->assertHeaders($data['headers']);
        } catch (InvalidImportFileException $e) {
            $job->fail($e->getMessage());
            $this->jobs->saveProgress($job);

            return;
        }

        $job->start(\count($data['rows']));
        $this->jobs->saveProgress($job);
        $this->em->clear();

        foreach ($data['rows'] as $rowNumber => $row) {
            $externalCode = '' === ($row[ProductRowMapper::COL_EXTERNAL_CODE] ?? '') ? null : $row[ProductRowMapper::COL_EXTERNAL_CODE];
            try {
                $this->importRow($job, $row, $rowNumber);
            } catch (RowValidationException $e) {
                foreach ($e->errors as $error) {
                    $job->addError($error['message'], $rowNumber, $e->externalCode, $error['field']);
                }
                $job->markRowFailed();
            } catch (\Throwable $e) {
                $this->logger->error('Import row failed', ['job' => $job->getId(), 'row' => $rowNumber, 'exception' => $e]);
                $job->addError('Не удалось сохранить товар: '.$e->getMessage(), $rowNumber, $externalCode);
                $job->markRowFailed();

                if (!$this->em->isOpen()) {
                    // A failed flush closes the EntityManager — continuing is impossible within this process.
                    $job->fail('Импорт прерван из-за ошибки базы данных');
                    $this->jobs->saveProgress($job);

                    return;
                }
            } finally {
                $this->em->clear();
            }
            $this->jobs->saveProgress($job);
        }

        $job->complete();
        $this->jobs->saveProgress($job);
    }

    /**
     * @param array<string, string> $row
     */
    private function importRow(ImportJob $job, array $row, int $rowNumber): void
    {
        $dto = $this->mapper->map($row, $rowNumber);
        foreach ($dto->warnings as $warning) {
            $job->addError($warning['message'], $rowNumber, $dto->externalCode, $warning['field'], ImportJob::LEVEL_WARNING);
        }

        // Network I/O happens before the DB transaction to keep the transaction short.
        $images = [];
        foreach ($this->images->downloadAll($dto->imageUrls) as $result) {
            if (!$result->isSuccessful()) {
                $job->addError(
                    \sprintf('Изображение %s не загружено: %s', $result->url, $result->error),
                    $rowNumber,
                    $dto->externalCode,
                    'image',
                    ImportJob::LEVEL_WARNING,
                );
            }
            $images[] = ['url' => $result->url, 'path' => $result->path];
        }

        $this->upsert($dto, $images) ? $job->markRowCreated() : $job->markRowUpdated();
    }

    /**
     * Writes the product with its attributes and images atomically.
     *
     * @param list<array{url: string, path: ?string}> $images
     *
     * @return bool true when a new product was created, false when an existing one was updated
     */
    private function upsert(ProductRowDto $dto, array $images): bool
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $product = $this->products->findByExternalCode($dto->externalCode);
            $isNew = null === $product;
            $product ??= new Product($dto->externalCode);

            $product->setName($dto->name)
                ->setDescription($dto->description)
                ->setPrice($dto->price)
                ->setDiscount($dto->discount);
            $product->replaceAttributes($dto->attributes);
            $product->replaceImages($images);

            $this->products->save($product);
            $connection->commit();

            return $isNew;
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }
}
