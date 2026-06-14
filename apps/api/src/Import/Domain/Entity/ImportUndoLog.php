<?php

declare(strict_types=1);

namespace App\Import\Domain\Entity;

use App\Import\Domain\Enum\ImportUndoOperation;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * IMP2-2.4 — one reversible change an import made to a PRE-EXISTING object,
 * captured BEFORE the write so "Wycofaj import" can restore the prior state
 * (not just delete created objects). Created objects carry no undo rows — the
 * rollback simply deletes them by `import_session_id` (D11).
 *
 * `payload` holds the before-state, shape per {@see ImportUndoOperation}:
 *   - ValueOverwritten: `{value, provenance, provenance_meta}` (restore the row)
 *   - ValueCreated:     `{}` (tombstone — rollback deletes the value row)
 *   - ObjectFieldChanged: `{status, enabled, parent_id, variant_axes}`
 *   - CategorySet:      `{categories: [{id, position, is_primary}, …]}`
 *   - RelationCreated:  `{target_object_ids: […]}` (rollback removes those links)
 *
 * First-write-wins per (session, object, operation, attribute, locale, channel):
 * a resumed/redelivered chunk (IMP2-2.3) must not overwrite the original
 * before-state — the repository INSERTs with ON CONFLICT DO NOTHING.
 */
class ImportUndoLog implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private ImportSession $importSession;

    /** Bare uuid — the object this change touched (a pre-existing object). */
    private Uuid $objectId;

    private string $operation;

    private ?string $attributeCode = null;

    private ?string $locale = null;

    private ?Uuid $channelId = null;

    /** @var array<string, mixed> before-state, shape per operation */
    private array $payload;

    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        ImportSession $importSession,
        Uuid $objectId,
        ImportUndoOperation $operation,
        array $payload,
        ?string $attributeCode = null,
        ?string $locale = null,
        ?Uuid $channelId = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->importSession = $importSession;
        $this->objectId = $objectId;
        $this->operation = $operation->value;
        $this->payload = $payload;
        $this->attributeCode = $attributeCode;
        $this->locale = $locale;
        $this->channelId = $channelId;
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

    public function getImportSession(): ImportSession
    {
        return $this->importSession;
    }

    public function getObjectId(): Uuid
    {
        return $this->objectId;
    }

    public function getOperation(): ImportUndoOperation
    {
        return ImportUndoOperation::from($this->operation);
    }

    public function getAttributeCode(): ?string
    {
        return $this->attributeCode;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getChannelId(): ?Uuid
    {
        return $this->channelId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
