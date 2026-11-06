<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
#[ORM\Index(name: 'import_jobs_status_idx', columns: ['status'])]
class ImportJob
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'user_id', type: 'integer', nullable: true)]
    private ?int $userId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::PENDING;

    #[ORM\Column(name: 'original_filename', type: 'string', length: 255)]
    private string $originalFilename;

    #[ORM\Column(name: 'stored_path', type: 'string', length: 500)]
    private string $storedPath;

    #[ORM\Column(name: 'total_rows', type: 'integer')]
    private int $totalRows = 0;

    #[ORM\Column(name: 'processed_rows', type: 'integer')]
    private int $processedRows = 0;

    #[ORM\Column(name: 'created_count', type: 'integer')]
    private int $createdCount = 0;

    #[ORM\Column(name: 'updated_count', type: 'integer')]
    private int $updatedCount = 0;

    #[ORM\Column(name: 'error_count', type: 'integer')]
    private int $errorCount = 0;

    #[ORM\Column(name: 'warning_count', type: 'integer')]
    private int $warningCount = 0;

    /** @var list<array{row: int, externalCode: string|null, message: string, level: string}> */
    #[ORM\Column(type: 'json')]
    private array $errors = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(string $id, ?int $userId, string $originalFilename, string $storedPath)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->originalFilename = $originalFilename;
        $this->storedPath = $storedPath;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function originalFilename(): string
    {
        return $this->originalFilename;
    }

    public function storedPath(): string
    {
        return $this->storedPath;
    }

    public function totalRows(): int
    {
        return $this->totalRows;
    }

    public function processedRows(): int
    {
        return $this->processedRows;
    }

    public function createdCount(): int
    {
        return $this->createdCount;
    }

    public function updatedCount(): int
    {
        return $this->updatedCount;
    }

    public function errorCount(): int
    {
        return $this->errorCount;
    }

    public function warningCount(): int
    {
        return $this->warningCount;
    }

    /** @return list<array{row: int, externalCode: string|null, message: string, level: string}> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function begin(): void
    {
        if ($this->status === self::COMPLETED || $this->status === self::FAILED) {
            return;
        }
        if ($this->status === self::PROCESSING) {
            $this->processedRows = 0;
            $this->createdCount = 0;
            $this->updatedCount = 0;
            $this->errorCount = 0;
            $this->warningCount = 0;
            $this->errors = [];
            $this->totalRows = 0;
        }
        $this->status = self::PROCESSING;
        $this->startedAt = new \DateTimeImmutable();
        $this->finishedAt = null;
        $this->message = null;
    }

    public function setTotalRows(int $total): void
    {
        $this->totalRows = $total;
    }

    public function incrementCreated(): void
    {
        ++$this->createdCount;
    }

    public function incrementUpdated(): void
    {
        ++$this->updatedCount;
    }

    public function incrementProcessed(): void
    {
        ++$this->processedRows;
    }

    public function addIssue(int $row, ?string $externalCode, string $message, string $level): void
    {
        if (count($this->errors) < 500) {
            $this->errors[] = [
                'row' => $row,
                'externalCode' => $externalCode,
                'message' => $message,
                'level' => $level,
            ];
        }
        if ($level === 'warning') {
            ++$this->warningCount;
        } else {
            ++$this->errorCount;
        }
    }

    public function finish(): void
    {
        $this->status = self::COMPLETED;
        $this->finishedAt = new \DateTimeImmutable();
        $this->message = sprintf(
            'Создано: %d, обновлено: %d, ошибок: %d, предупреждений: %d',
            $this->createdCount,
            $this->updatedCount,
            $this->errorCount,
            $this->warningCount,
        );
    }

    public function fail(string $message): void
    {
        $this->status = self::FAILED;
        $this->finishedAt = new \DateTimeImmutable();
        $this->message = $message;
    }

    public function progress(): int
    {
        if ($this->status === self::COMPLETED) {
            return 100;
        }
        if ($this->totalRows === 0) {
            return 0;
        }

        return (int) floor($this->processedRows * 100 / $this->totalRows);
    }
}
