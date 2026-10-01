<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Asynchronous import task: progress + error report polled by the client.
 *
 * @phpstan-type ImportError array{row: ?int, externalCode: ?string, field: ?string, message: string, level: string}
 */
#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
#[ORM\Index(name: 'idx_import_jobs_created_at', columns: ['created_at'])]
class ImportJob
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const LEVEL_ERROR = 'error';
    public const LEVEL_WARNING = 'warning';

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'original_filename', type: Types::STRING, length: 255)]
    private string $originalFilename;

    #[ORM\Column(name: 'file_path', type: Types::STRING, length: 512)]
    private string $filePath;

    #[ORM\Column(name: 'total_rows', type: Types::INTEGER, options: ['default' => 0])]
    private int $totalRows = 0;

    #[ORM\Column(name: 'processed_rows', type: Types::INTEGER, options: ['default' => 0])]
    private int $processedRows = 0;

    #[ORM\Column(name: 'created_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $createdCount = 0;

    #[ORM\Column(name: 'updated_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $updatedCount = 0;

    #[ORM\Column(name: 'failed_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $failedCount = 0;

    /** @var list<ImportError> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $errors = [];

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(string $originalFilename, string $filePath, ?string $id = null)
    {
        $this->id = $id ?? Uuid::v4()->toRfc4122();
        $this->originalFilename = $originalFilename;
        $this->filePath = $filePath;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function start(int $totalRows): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->totalRows = $totalRows;
        $this->processedRows = 0;
        $this->createdCount = 0;
        $this->updatedCount = 0;
        $this->failedCount = 0;
        $this->errors = [];
        $this->startedAt = new \DateTimeImmutable();
        $this->finishedAt = null;
    }

    public function markRowCreated(): void
    {
        ++$this->createdCount;
        ++$this->processedRows;
    }

    public function markRowUpdated(): void
    {
        ++$this->updatedCount;
        ++$this->processedRows;
    }

    public function markRowFailed(): void
    {
        ++$this->failedCount;
        ++$this->processedRows;
    }

    public function addError(string $message, ?int $row = null, ?string $externalCode = null, ?string $field = null): void
    {
        $this->addReportEntry(self::LEVEL_ERROR, $message, $row, $externalCode, $field);
    }

    /** Non-fatal problem: the row was imported, but something (e.g. an image) was skipped. */
    public function addWarning(string $message, ?int $row = null, ?string $externalCode = null, ?string $field = null): void
    {
        $this->addReportEntry(self::LEVEL_WARNING, $message, $row, $externalCode, $field);
    }

    private function addReportEntry(string $level, string $message, ?int $row, ?string $externalCode, ?string $field): void
    {
        $this->errors[] = [
            'row' => $row,
            'externalCode' => $externalCode,
            'field' => $field,
            'message' => $message,
            'level' => $level,
        ];
    }

    public function complete(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function fail(string $reason): void
    {
        $this->addError($reason);
        $this->status = self::STATUS_FAILED;
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function isFinished(): bool
    {
        return \in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function getProgress(): int
    {
        if ($this->isFinished()) {
            return 100;
        }

        return $this->totalRows > 0 ? (int) floor($this->processedRows * 100 / $this->totalRows) : 0;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    /** @return list<ImportError> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }
}
