<?php

declare(strict_types=1);

namespace App\Import\Domain\Entity;

use App\Backup\Domain\Entity\Backup;
use App\Catalog\Domain\Entity\ObjectType;
use App\Import\Domain\Enum\ImportImageSource;
use App\Import\Domain\Enum\ImportMode;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One execution of the self-service import wizard.
 *
 * Carries the audit trail of a single upload: file metadata, the active
 * mapping, counts populated by the worker, and the rollback window.
 * The 24h `rollback_until` is the contract guarded by IMP-05; the user
 * may soft-delete every {@see \App\Catalog\Domain\Entity\CatalogObject}
 * stamped with this session's id within that window.
 *
 * Events are intentionally NOT recorded here in IMP-01 — IMP-04 will
 * populate {@see recordThat()} calls once the async handler emits the
 * Mercure progress channel events.
 */
class ImportSession extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    /** Bare uuid to avoid cross-BC coupling with Identity\Domain\Entity\User. */
    private Uuid $userId;

    private ?ImportProfile $profile = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $fileName;

    private int $fileSizeBytes;

    private ?string $zipFileName = null;

    private ?int $zipFileSizeBytes = null;

    /** IMP2-1.13 — media source for Asset cells: http | zip | none. */
    private string $imageSource = ImportImageSource::None->value;

    private ObjectType $targetObjectType;

    private string $status = ImportSessionStatus::Pending->value;

    private ?int $totalRows = null;

    private int $successCount = 0;

    private int $errorCount = 0;

    /** ADR-0019 D3 — write strategy of THIS run (profile only pre-fills it). */
    private string $mode = ImportMode::Upsert->value;

    /** ADR-0019 D1 — identifier-attribute match key; null = objects.code (SKU). */
    private ?string $matchAttributeCode = null;

    private int $updatedCount = 0;

    private int $skippedCount = 0;

    private int $imagesDownloaded = 0;

    private int $imagesFailed = 0;

    /**
     * IMP2-1.12 — number of media (image-download) batches dispatched for this
     * run that have not yet finished. While > 0 the session is NOT finalized:
     * the row phase may be done but downloads are still in flight (async
     * `import` transport). The last batch to finish finalizes the session.
     */
    private int $pendingImageBatches = 0;

    /**
     * IMP2-1.12 — true once the row-write phase has dispatched every media
     * batch. A media handler may only finalize the session after this flag is
     * set (otherwise an early-finishing batch could complete a session whose
     * later chunks are still importing).
     */
    private bool $rowPhaseComplete = false;

    private ?DateTimeImmutable $startedAt = null;

    private ?DateTimeImmutable $completedAt = null;

    private ?DateTimeImmutable $rollbackUntil = null;

    private ?DateTimeImmutable $rolledBackAt = null;

    private ?Backup $backupSnapshot = null;

    private ?string $errorMessage = null;

    /**
     * IMP-04 (#445) — header → attribute_code mapping captured at start
     * time. Lives on the session (not just the optional profile) so
     * profile-less ad-hoc imports keep their mapping after dispatch.
     *
     * @var array<string, string>
     */
    private array $columnMapping = [];

    private DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $userId,
        ObjectType $targetObjectType,
        string $fileName,
        int $fileSizeBytes,
        ?ImportProfile $profile = null,
        ?string $zipFileName = null,
        ?int $zipFileSizeBytes = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->userId = $userId;
        $this->targetObjectType = $targetObjectType;
        $this->fileName = $fileName;
        $this->fileSizeBytes = $fileSizeBytes;
        $this->profile = $profile;
        $this->zipFileName = $zipFileName;
        $this->zipFileSizeBytes = $zipFileSizeBytes;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * @internal stamped by TenantAssignmentListener on prePersist
     */
    public function assignTenant(Tenant $tenant): void
    {
        if (null !== $this->tenant) {
            throw new LogicException('Tenant is already assigned and cannot be reassigned.');
        }
        $this->tenant = $tenant;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getProfile(): ?ImportProfile
    {
        return $this->profile;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getFileSizeBytes(): int
    {
        return $this->fileSizeBytes;
    }

    public function getZipFileName(): ?string
    {
        return $this->zipFileName;
    }

    public function getZipFileSizeBytes(): ?int
    {
        return $this->zipFileSizeBytes;
    }

    public function getImageSource(): ImportImageSource
    {
        return ImportImageSource::from($this->imageSource);
    }

    public function setImageSource(ImportImageSource $imageSource): void
    {
        $this->imageSource = $imageSource->value;
    }

    public function getTargetObjectType(): ObjectType
    {
        return $this->targetObjectType;
    }

    public function getStatus(): ImportSessionStatus
    {
        return ImportSessionStatus::from($this->status);
    }

    public function getTotalRows(): ?int
    {
        return $this->totalRows;
    }

    public function setTotalRows(int $totalRows): void
    {
        $this->totalRows = $totalRows;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getImagesDownloaded(): int
    {
        return $this->imagesDownloaded;
    }

    public function getImagesFailed(): int
    {
        return $this->imagesFailed;
    }

    public function incrementSuccess(): void
    {
        ++$this->successCount;
    }

    public function getMode(): ImportMode
    {
        return ImportMode::from($this->mode);
    }

    public function configureRun(ImportMode $mode, ?string $matchAttributeCode): void
    {
        $this->mode = $mode->value;
        $this->matchAttributeCode = $matchAttributeCode;
    }

    public function getMatchAttributeCode(): ?string
    {
        return $this->matchAttributeCode;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function incrementUpdated(): void
    {
        ++$this->updatedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function incrementSkipped(): void
    {
        ++$this->skippedCount;
    }

    public function incrementError(): void
    {
        ++$this->errorCount;
    }

    public function incrementImagesDownloaded(): void
    {
        ++$this->imagesDownloaded;
    }

    public function incrementImagesFailed(): void
    {
        ++$this->imagesFailed;
    }

    public function getPendingImageBatches(): int
    {
        return $this->pendingImageBatches;
    }

    public function incrementPendingImageBatches(): void
    {
        ++$this->pendingImageBatches;
    }

    public function decrementPendingImageBatches(): void
    {
        $this->pendingImageBatches = max(0, $this->pendingImageBatches - 1);
    }

    /**
     * IMP2-1.12 — the row-write phase finished dispatching media batches.
     * Idempotent; the run handler calls it once at the end of the loop.
     */
    public function markRowPhaseComplete(): void
    {
        $this->rowPhaseComplete = true;
    }

    public function isRowPhaseComplete(): bool
    {
        return $this->rowPhaseComplete;
    }

    public function isAwaitingMedia(): bool
    {
        return $this->pendingImageBatches > 0;
    }

    /**
     * True when the session is ready to transition to its terminal state:
     * the row phase is done AND no media batch is still pending.
     */
    public function canFinalizeMedia(): bool
    {
        return $this->rowPhaseComplete && 0 === $this->pendingImageBatches;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getRollbackUntil(): ?DateTimeImmutable
    {
        return $this->rollbackUntil;
    }

    public function getRolledBackAt(): ?DateTimeImmutable
    {
        return $this->rolledBackAt;
    }

    public function getBackupSnapshot(): ?Backup
    {
        return $this->backupSnapshot;
    }

    public function setBackupSnapshot(?Backup $backup): void
    {
        $this->backupSnapshot = $backup;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array<string, string>
     */
    public function getColumnMapping(): array
    {
        return $this->columnMapping;
    }

    /**
     * @param array<string, string> $columnMapping
     */
    public function setColumnMapping(array $columnMapping): void
    {
        $this->columnMapping = $columnMapping;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markRunning(): void
    {
        $this->ensureTransitionable([ImportSessionStatus::Pending, ImportSessionStatus::Paused]);
        $this->status = ImportSessionStatus::Running->value;
        if (null === $this->startedAt) {
            $this->startedAt = new DateTimeImmutable();
        }
    }

    public function markPaused(): void
    {
        $this->ensureTransitionable([ImportSessionStatus::Running]);
        $this->status = ImportSessionStatus::Paused->value;
    }

    public function markCancelled(): void
    {
        $this->ensureTransitionable([
            ImportSessionStatus::Pending,
            ImportSessionStatus::Running,
            ImportSessionStatus::Paused,
        ]);
        $this->status = ImportSessionStatus::Cancelled->value;
        $this->completedAt = new DateTimeImmutable();
    }

    public function markCompleted(int $rollbackWindowHours = 24): void
    {
        $this->ensureTransitionable([ImportSessionStatus::Running]);
        $this->status = ($this->errorCount > 0 ? ImportSessionStatus::Partial : ImportSessionStatus::Success)->value;
        $this->completedAt = new DateTimeImmutable();
        $this->rollbackUntil = $this->completedAt->modify('+'.$rollbackWindowHours.' hours');
    }

    public function markFailed(string $message): void
    {
        $this->ensureTransitionable([
            ImportSessionStatus::Pending,
            ImportSessionStatus::Running,
            ImportSessionStatus::Paused,
        ]);
        $this->status = ImportSessionStatus::Failed->value;
        $this->completedAt = new DateTimeImmutable();
        $this->errorMessage = $message;
    }

    public function markRolledBack(): void
    {
        if (!$this->getStatus()->isRollbackable()) {
            throw new LogicException(\sprintf(
                'Import session %s cannot be rolled back from status "%s".',
                $this->id->toRfc4122(),
                $this->status,
            ));
        }
        if (null === $this->rollbackUntil || $this->rollbackUntil < new DateTimeImmutable()) {
            throw new LogicException(\sprintf(
                'Rollback window for import session %s has expired.',
                $this->id->toRfc4122(),
            ));
        }
        $this->status = ImportSessionStatus::RolledBack->value;
        $this->rolledBackAt = new DateTimeImmutable();
    }

    public function isWithinRollbackWindow(?DateTimeImmutable $now = null): bool
    {
        if (null === $this->rollbackUntil) {
            return false;
        }

        return $this->rollbackUntil > ($now ?? new DateTimeImmutable());
    }

    /**
     * @param list<ImportSessionStatus> $allowed
     */
    private function ensureTransitionable(array $allowed): void
    {
        $current = $this->getStatus();
        foreach ($allowed as $status) {
            if ($status === $current) {
                return;
            }
        }
        throw new LogicException(\sprintf(
            'Cannot transition import session %s from "%s".',
            $this->id->toRfc4122(),
            $this->status,
        ));
    }
}
